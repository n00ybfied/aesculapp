<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\CustomerAccountDeletionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiCustomerAccountDeletionController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CustomerAccountDeletionService $deletion,
    ) {
    }

    #[Route('/api/v1/profile', name: 'api_v1_profile_delete', methods: ['DELETE'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        if ($membership === null || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return new JsonResponse(['message' => 'Bitte geben Sie Ihr aktuelles Passwort ein.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $password = $data['password'] ?? null;
        if (!is_string($password) || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Das aktuelle Passwort ist nicht korrekt.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->deletion->delete($user, $this->activeTenant->get());
        } catch (\DomainException) {
            return new JsonResponse(['message' => 'Der Kundenzugang wurde bereits entfernt.'], Response::HTTP_CONFLICT);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
