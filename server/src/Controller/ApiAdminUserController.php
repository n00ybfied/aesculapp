<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StaffInvitation;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\StaffInvitationRepository;
use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\TenantMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
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
        if (!is_string($email) || !is_string($displayName) || !is_string($role)) {
            return $this->validationError();
        }

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
        if ($this->users->findOneByEmail($email) !== null) {
            return new JsonResponse(['message' => 'Für diese E-Mail-Adresse existiert bereits ein Benutzerkonto.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tenant = $this->activeTenant->get();
        $rawToken = bin2hex(random_bytes(32));
        $invitation = new StaffInvitation($tenant, $email, $displayName, $roles, hash('sha256', $rawToken));
        $this->entityManager->persist($invitation);
        $acceptanceUrl = rtrim($this->adminUrl, '/').'/einladung-annehmen?token='.rawurlencode($rawToken);

        try {
            $this->mailer->send(
                $tenant,
                (new Email())
                    ->from($this->mailFrom)
                    ->to($email)
                    ->subject('Einladung zum Aesculapp Apothekenportal')
                    ->text("Sie wurden zum Apothekenportal eingeladen. Legen Sie innerhalb von sieben Tagen Ihr Passwort fest:\n{$acceptanceUrl}"),
                'admin_invitation',
            );
            $this->entityManager->flush();
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Die Einladung konnte derzeit nicht versendet werden.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['invitation' => $this->serializeInvitation($invitation)], Response::HTTP_CREATED);
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

    /** @return array{id:int,displayName:string,email:string,roles:list<string>} */
    private function serializeMembership(TenantMembership $membership): array
    {
        $user = $membership->getUser();

        return ['id' => $user->getId(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail(), 'roles' => $membership->getRoles()];
    }

    /** @return array{id:int,displayName:string,email:string,roles:list<string>,createdAt:string} */
    private function serializeInvitation(StaffInvitation $invitation): array
    {
        return ['id' => $invitation->getId(), 'displayName' => $invitation->getDisplayName(), 'email' => $invitation->getEmail(), 'roles' => $invitation->getRoles(), 'createdAt' => $invitation->getCreatedAt()->format(DATE_ATOM)];
    }

    private function validationError(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte prüfen Sie Name, E-Mail-Adresse und Rolle.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
