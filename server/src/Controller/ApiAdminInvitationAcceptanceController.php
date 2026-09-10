<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\StaffInvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminInvitationAcceptanceController
{
    #[Route('/api/v1/admin/invitations/accept', name: 'api_v1_admin_invitation_accept', methods: ['POST'])]
    public function __invoke(
        Request $request,
        StaffInvitationRepository $invitations,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->invalidResponse();
        }
        $token = $payload['token'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($token) || $token === '' || !is_string($password) || mb_strlen($password) < 10) {
            return $this->invalidResponse();
        }

        $invitation = $invitations->findUsableByTokenHash(hash('sha256', $token));
        if ($invitation === null || $users->findOneByEmail($invitation->getEmail()) !== null) {
            return $this->invalidResponse();
        }

        $user = new User($invitation->getEmail(), $invitation->getEmail(), $invitation->getDisplayName());
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setActive(true);
        $invitation->markAccepted();
        $entityManager->persist($user);
        $entityManager->persist(new TenantMembership($invitation->getTenant(), $user, $invitation->getRoles()));
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function invalidResponse(): JsonResponse
    {
        return new JsonResponse(['message' => 'Die Einladung ist ungültig, abgelaufen oder wurde bereits angenommen.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
