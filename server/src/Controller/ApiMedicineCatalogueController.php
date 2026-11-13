<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\{ActiveTenantProvider, MedicineCatalogue};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApiMedicineCatalogueController
{
    public function __construct(private readonly Security $security, private readonly ActiveTenantProvider $tenant, private readonly TenantMembershipRepository $memberships, private readonly MedicineCatalogue $catalogue)
    {
    }

    #[Route('/api/v1/medicines/search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $tenant = $this->tenant->get();
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $tenant) : null;
        if ($membership === null || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) throw new HttpException(Response::HTTP_FORBIDDEN);
        $payload = json_decode($request->getContent(), true);
        $query = is_array($payload) ? ($payload['q'] ?? null) : null;
        if (!is_string($query)) throw new HttpException(Response::HTTP_BAD_REQUEST);
        return new JsonResponse(['medicines' => $this->catalogue->search($query, (int) $tenant->getId()), 'catalogueReady' => $this->catalogue->imported()], Response::HTTP_OK, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/admin/medicines/debug', methods: ['GET'])]
    public function debug(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $tenant = $this->tenant->get();
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $tenant) : null;
        if ($membership === null || !in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true)) throw new HttpException(Response::HTTP_FORBIDDEN);
        $query = $request->query->get('q', '');
        if (!is_string($query)) throw new HttpException(Response::HTTP_BAD_REQUEST);
        return new JsonResponse($this->catalogue->debug($query, (int) $tenant->getId()), Response::HTTP_OK, ['Cache-Control' => 'private, no-store']);
    }
}
