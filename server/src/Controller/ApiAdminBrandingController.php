<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminBrandingController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(private readonly ActiveTenantProvider $activeTenant, private readonly TenantMembershipRepository $memberships, private readonly Security $security, private readonly EntityManagerInterface $entityManager) {}

    #[Route('/api/v1/admin/settings/branding', name: 'api_v1_admin_branding', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) { return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN); }
        return new JsonResponse(['branding' => $this->serialize($this->activeTenant->get(), $request)]);
    }

    #[Route('/api/v1/branding', name: 'api_v1_branding', methods: ['GET'])]
    public function publicGet(Request $request): JsonResponse
    {
        return new JsonResponse(['branding' => $this->serialize($this->activeTenant->get(), $request)]);
    }

    #[Route('/api/v1/admin/settings/branding', name: 'api_v1_admin_branding_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) { return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN); }
        $tenant = $this->activeTenant->get();
        foreach (['logo' => 'LogoPath', 'squareLogo' => 'SquareLogoPath', 'favicon' => 'FaviconPath'] as $field => $property) {
            $image = $request->files->get($field);
            if (!$image instanceof UploadedFile) { continue; }
            $path = $this->storeImage($image, $tenant, $field === 'favicon');
            if ($path === null) { return new JsonResponse(['message' => 'Bitte verwenden Sie PNG, JPEG oder WebP; für das Favicon ist zusätzlich ICO erlaubt. Maximale Dateigröße: 5 MB.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
            $getter = 'get'.$property;
            $previous = $tenant->$getter();
            $setter = 'set'.$property;
            $tenant->$setter($path);
            $this->deleteFile($previous);
        }
        $this->entityManager->flush();
        return new JsonResponse(['branding' => $this->serialize($tenant, $request)]);
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) { return false; }
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }

    private function storeImage(UploadedFile $image, Tenant $tenant, bool $favicon): ?string
    {
        if ($image->getSize() > 5 * 1024 * 1024) { return null; }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($favicon) { $extensions += ['image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico']; }
        $extension = $extensions[$image->getMimeType()] ?? null;
        if ($extension === null) { return null; }
        $relativeDirectory = '/uploads/tenant-branding/'.$tenant->getId();
        $directory = dirname(__DIR__, 2).'/public'.$relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { return null; }
        $filename = bin2hex(random_bytes(12)).'.'.$extension;
        $image->move($directory, $filename);
        return $relativeDirectory.'/'.$filename;
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && is_file(dirname(__DIR__, 2).'/public'.$path)) { unlink(dirname(__DIR__, 2).'/public'.$path); }
    }

    /** @return array{logoUrl:?string,squareLogoUrl:?string,faviconUrl:?string} */
    private function serialize(Tenant $tenant, Request $request): array
    {
        $origin = $request->getSchemeAndHttpHost();
        return ['logoUrl' => $tenant->getLogoPath() ? $origin.$tenant->getLogoPath() : null, 'squareLogoUrl' => $tenant->getSquareLogoPath() ? $origin.$tenant->getSquareLogoPath() : null, 'faviconUrl' => $tenant->getFaviconPath() ? $origin.$tenant->getFaviconPath() : null];
    }
}
