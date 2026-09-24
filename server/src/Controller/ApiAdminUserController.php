<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StaffInvitation;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\StaffInvitationRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AdminAreaPermissions;
use App\Service\TenantMailer;
use App\Service\UsernameReservation;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminUserController
{
    private const STAFF_ROLE = 'ROLE_TENANT_STAFF';
    private const ADMIN_ROLE = 'ROLE_TENANT_ADMIN';

    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly StaffInvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantMailer $mailer,
        private readonly AdminAreaPermissions $areaPermissions,
        private readonly string $adminUrl,
        private readonly string $mailFrom,
    ) {
    }

    #[Route('/api/v1/admin/users', name: 'api_v1_admin_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if ($this->currentMembership() === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $this->activeTenant->get();
        $memberships = $this->memberships->createQueryBuilder('membership')
            ->join('membership.user', 'user')
            ->andWhere('membership.tenant = :tenant')
            ->andWhere('membership.roles LIKE :staffRole OR membership.roles LIKE :adminRole')
            ->setParameter('tenant', $tenant)
            ->setParameter('staffRole', '%'.self::STAFF_ROLE.'%')
            ->setParameter('adminRole', '%'.self::ADMIN_ROLE.'%')
            ->orderBy('user.displayName', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'users' => array_map(fn (TenantMembership $membership) => $this->serializeMembership($membership), $memberships),
            'invitations' => array_map(fn (StaffInvitation $invitation) => $this->serializeInvitation($invitation), $this->invitations->findPendingForTenant($tenant)),
        ]);
    }

    #[Route('/api/v1/admin/users/invitations', name: 'api_v1_admin_users_invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $inviter = $this->currentMembership();
        if ($inviter === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->validationError();
        }
        $email = $payload['email'] ?? null;
        $displayName = $payload['displayName'] ?? null;
        $role = $payload['role'] ?? null;
        $permissions = $payload['permissions'] ?? array_values(array_filter(
            AdminAreaPermissions::DEFAULT_STAFF_AREAS,
            fn (string $area): bool => $this->areaPermissions->can($inviter, $area),
        ));
        if (!is_string($email) || !is_string($displayName) || !is_string($role)) {
            return $this->validationError();
        }
        if (!is_array($permissions) || array_filter($permissions, fn ($item) => !is_string($item) || !in_array($item, AdminAreaPermissions::AREAS, true)) !== []) {
            return $this->validationError();
        }
        $permissions = array_values(array_unique($permissions));

        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);
        $roles = match ($role) {
            'staff' => [self::STAFF_ROLE],
            'admin' => [self::ADMIN_ROLE],
            default => [],
        };
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180 || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 160 || $roles === []) {
            return $this->validationError();
        }
        if ($roles === [self::ADMIN_ROLE] && !in_array(self::ADMIN_ROLE, $inviter->getRoles(), true)) {
            return new JsonResponse(['message' => 'Only tenant administrators may invite further administrators.'], Response::HTTP_FORBIDDEN);
        }
        foreach ($permissions as $area) {
            if (!$this->areaPermissions->can($inviter, $area)) {
                return new JsonResponse(['message' => 'Sie dürfen keine eigenen Berechtigungen weitergeben, die Sie nicht besitzen.'], Response::HTTP_FORBIDDEN);
            }
        }
        $tenant = $this->activeTenant->get();
        $existingUser = $this->users->findOneByEmail($email);
        if ($existingUser !== null) {
            $existingMembership = $this->memberships->findForUserAndTenant($existingUser, $tenant);
            $hasAdministrativeAccess = $existingMembership !== null
                && [] !== array_intersect([self::STAFF_ROLE, self::ADMIN_ROLE], $existingMembership->getRoles());
            $isAlreadyAdministrator = $existingMembership !== null
                && in_array(self::ADMIN_ROLE, $existingMembership->getRoles(), true);

            if ($hasAdministrativeAccess && ($roles === [self::STAFF_ROLE] || $isAlreadyAdministrator)) {
                return new JsonResponse(['message' => 'Dieses Benutzerkonto hat bereits den gewünschten Mitarbeiterzugang.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $rawToken = bin2hex(random_bytes(32));
        $invitation = new StaffInvitation($tenant, $email, $displayName, $roles, hash('sha256', $rawToken));
        $invitation->setPermissions($roles === [self::ADMIN_ROLE] ? null : $permissions);
        $this->entityManager->persist($invitation);
        $acceptanceUrl = rtrim($this->adminUrl, '/').'/einladung-annehmen?token='.rawurlencode($rawToken);

        try {
            $this->mailer->send(
                $tenant,
                (new Email())
                    ->from($this->mailFrom)
                    ->to($email)
                    ->subject('Einladung zum Aesculapp Apothekenportal')
                    ->text($existingUser === null
                        ? "Sie wurden zum Apothekenportal eingeladen. Legen Sie innerhalb von sieben Tagen Ihr Passwort fest:\n{$acceptanceUrl}"
                        : "Sie wurden zum Apothekenportal eingeladen. Bestätigen Sie innerhalb von sieben Tagen Ihren zusätzlichen Mitarbeiterzugang. Ihr bestehendes Passwort bleibt unverändert:\n{$acceptanceUrl}"),
                'admin_invitation',
            );
            $this->entityManager->flush();
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Die Einladung konnte derzeit nicht versendet werden.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['invitation' => $this->serializeInvitation($invitation)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/users', name: 'api_v1_admin_users_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UsernameReservation $usernameReservation,
    ): JsonResponse {
        $actor = $this->currentMembership();
        if ($actor === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->validationError();
        }
        $email = $payload['email'] ?? null;
        $displayName = $payload['displayName'] ?? null;
        $password = $payload['password'] ?? null;
        $role = $payload['role'] ?? null;
        $permissions = $payload['permissions'] ?? AdminAreaPermissions::DEFAULT_STAFF_AREAS;
        if (!is_string($email) || !is_string($displayName) || !is_string($password) || !is_string($role)
            || !is_array($permissions)
            || array_filter($permissions, fn ($area) => !is_string($area) || !in_array($area, AdminAreaPermissions::AREAS, true)) !== []) {
            return $this->validationError();
        }

        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 100
            || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 160 || mb_strlen($password) < 10
            || !in_array($role, ['staff', 'admin'], true)) {
            return new JsonResponse(['message' => 'Bitte prüfen Sie Name, E-Mail-Adresse, Rolle und Passwort (mindestens 10 Zeichen).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($role === 'admin' && !in_array(self::ADMIN_ROLE, $actor->getRoles(), true)) {
            return new JsonResponse(['message' => 'Nur Administratoren dürfen weitere Administratoren anlegen.'], Response::HTTP_FORBIDDEN);
        }
        foreach ($permissions as $area) {
            if (!$this->areaPermissions->can($actor, $area)) {
                return new JsonResponse(['message' => 'Sie dürfen keine eigenen Berechtigungen weitergeben, die Sie nicht besitzen.'], Response::HTTP_FORBIDDEN);
            }
        }
        if ($this->users->findOneByEmail($email) !== null || $this->users->findOneByUsername($email) !== null) {
            return new JsonResponse(['message' => 'Für diese E-Mail-Adresse besteht bereits ein Konto. Bitte verwenden Sie die Einladung.'], Response::HTTP_CONFLICT);
        }
        if ($usernameReservation->isReserved($email)) {
            return new JsonResponse(['message' => 'Diese Benutzerkennung ist vorübergehend reserviert. Bitte versuchen Sie es später erneut.'], Response::HTTP_CONFLICT);
        }

        $user = new User($email, $email, $displayName);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setActive(true);
        $membership = new TenantMembership($this->activeTenant->get(), $user, [$role === 'admin' ? self::ADMIN_ROLE : self::STAFF_ROLE]);
        $membership->setPermissions($role === 'admin' ? null : array_values(array_unique($permissions)));
        $this->entityManager->persist($user);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return new JsonResponse(['user' => $this->serializeMembership($membership)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/users/{id}/permissions', name: 'api_v1_admin_users_permissions', methods: ['PATCH'], requirements: ['id' => '\\d+'])]
    public function updatePermissions(int $id, Request $request): JsonResponse
    {
        $actor = $this->currentMembership();
        if ($actor === null || !in_array(self::ADMIN_ROLE, $actor->getRoles(), true)) {
            return new JsonResponse(['message' => 'Nur Administratoren dürfen bestehende Berechtigungen ändern.'], Response::HTTP_FORBIDDEN);
        }
        try { $payload = $request->toArray(); } catch (JsonException) { return $this->validationError(); }
        $permissions = $payload['permissions'] ?? null;
        if (!is_array($permissions) || array_filter($permissions, fn ($item) => !is_string($item) || !in_array($item, AdminAreaPermissions::AREAS, true)) !== []) {
            return $this->validationError();
        }
        $user = $this->users->find($id);
        $membership = $user === null ? null : $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        if ($membership === null) return new JsonResponse(['message' => 'Benutzer nicht gefunden.'], Response::HTTP_NOT_FOUND);
        if (!in_array(self::STAFF_ROLE, $membership->getRoles(), true)) {
            return new JsonResponse(['message' => 'Das Konto hat keinen Mitarbeiterzugang.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (in_array(self::ADMIN_ROLE, $membership->getRoles(), true)) {
            return new JsonResponse(['message' => 'Administratoren besitzen immer Vollzugriff.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $membership->setPermissions(array_values(array_unique($permissions)));
        $this->entityManager->flush();
        return new JsonResponse(['user' => $this->serializeMembership($membership)]);
    }

    #[Route('/api/v1/admin/users/{id}/password', name: 'api_v1_admin_users_password', methods: ['PATCH'], requirements: ['id' => '\\d+'])]
    public function changePassword(
        int $id,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        RevokeRefreshTokenManagerInterface $refreshTokens,
        PasswordResetTokenRepository $resetTokens,
    ): JsonResponse {
        $actor = $this->currentMembership();
        if ($actor === null || !in_array(self::ADMIN_ROLE, $actor->getRoles(), true)) {
            return new JsonResponse(['message' => 'Nur Administratoren dürfen Mitarbeiterpasswörter ändern.'], Response::HTTP_FORBIDDEN);
        }
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return new JsonResponse(['message' => 'Bitte ein neues Passwort eingeben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $password = $payload['password'] ?? null;
        if (!is_string($password) || mb_strlen($password) < 10 || mb_strlen($password) > 4096) {
            return new JsonResponse(['message' => 'Das Passwort muss zwischen 10 und 4096 Zeichen lang sein.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user = $this->users->find($id);
        $membership = $user === null ? null : $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        if ($membership === null || [] === array_intersect([self::STAFF_ROLE, self::ADMIN_ROLE], $membership->getRoles())) {
            return new JsonResponse(['message' => 'Mitarbeiterkonto nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $resetTokens->invalidateForUser($user);
        $refreshTokens->revokeAllForUser($user);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function currentMembership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());

        return $membership !== null && ([] !== array_intersect([self::STAFF_ROLE, self::ADMIN_ROLE], $membership->getRoles())) ? $membership : null;
    }

    /** @return array{id:int,displayName:string,email:string,roles:list<string>,permissions:list<string>|null} */
    private function serializeMembership(TenantMembership $membership): array
    {
        $user = $membership->getUser();

        return ['id' => $user->getId(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail(), 'roles' => $membership->getRoles(), 'permissions' => $membership->getPermissions()];
    }

    /** @return array{id:int,displayName:string,email:string,roles:list<string>,permissions:list<string>|null,createdAt:string} */
    private function serializeInvitation(StaffInvitation $invitation): array
    {
        return ['id' => $invitation->getId(), 'displayName' => $invitation->getDisplayName(), 'email' => $invitation->getEmail(), 'roles' => $invitation->getRoles(), 'permissions' => $invitation->getPermissions(), 'createdAt' => $invitation->getCreatedAt()->format(DATE_ATOM)];
    }

    private function validationError(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte prüfen Sie Name, E-Mail-Adresse und Rolle.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
