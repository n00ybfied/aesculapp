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

    public function __construct(private readonly ActiveTenantProvider $activeTenant, private readonly TenantMembershipRepository $memberships, private readonly Security $security, private readonly EntityManagerInterface $entityManager, private readonly string $appSecret) {}

    #[Route('/api/v1/admin/settings/branding', name: 'api_v1_admin_branding', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) { return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN); }
        return new JsonResponse(['branding' => $this->serialize($this->activeTenant->get(), $request, true)]);
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
        $initialPoints = $request->request->get('initialPoints');
        if ($initialPoints !== null) {
            if (!is_string($initialPoints) || !ctype_digit($initialPoints) || (int) $initialPoints > 100000) { return new JsonResponse(['message' => 'Das Startguthaben muss zwischen 0 und 100.000 Punkten liegen.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
            $tenant->setInitialPoints((int) $initialPoints);
        }
        foreach (['birthdayBonusPoints' => 'setBirthdayBonusPoints', 'pointsPerEuro' => 'setPointsPerEuro'] as $field => $setter) { $value = $request->request->get($field); if ($value !== null) { if ($field === 'pointsPerEuro' && $request->request->get('confirmPointsPerEuroChange') !== 'true') { return new JsonResponse(['message' => 'Bitte bestätigen Sie die Änderung des Punkteverhältnisses.'], 422); } if (!is_string($value) || !ctype_digit($value) || (int) $value > 100000) { return new JsonResponse(['message' => 'Bitte prüfen Sie die Punkte-Einstellungen.'], 422); } $tenant->$setter((int) $value); } }
        $tenant->setAllowDuplicateReceiptImports($request->request->get('allowDuplicateReceiptImports') === 'true');
        $tenant->setShowCustomerDebugOutput($request->request->get('showCustomerDebugOutput') === 'true');
        $receiptQrPrefix = trim((string) $request->request->get('receiptQrPrefix', ''));
        if (mb_strlen($receiptQrPrefix) > 120 || str_contains($receiptQrPrefix, "\n") || str_contains($receiptQrPrefix, "\r")) {
            return new JsonResponse(['message' => 'Der Rechnungs-QR-Präfix ist ungültig.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $tenant->setReceiptQrPrefix($receiptQrPrefix === '' ? null : $receiptQrPrefix);
        $smtpHost = trim((string) $request->request->get('smtpHost', '')); $smtpFrom = trim((string) $request->request->get('smtpFrom', '')); $smtpPort = $request->request->get('smtpPort'); $smtpEncryption = $request->request->get('smtpEncryption');
        if ($smtpHost !== '' || $smtpFrom !== '') { if ($smtpHost === '' || false === filter_var($smtpFrom, FILTER_VALIDATE_EMAIL) || !is_string($smtpPort) || !ctype_digit($smtpPort) || (int) $smtpPort < 1 || (int) $smtpPort > 65535 || !is_string($smtpEncryption) || !in_array($smtpEncryption, ['tls','ssl','none'], true)) { return new JsonResponse(['message' => 'Bitte prüfen Sie die SMTP-Einstellungen.'], 422); } $tenant->setSmtpHost($smtpHost); $tenant->setSmtpPort((int) $smtpPort); $tenant->setSmtpEncryption($smtpEncryption); $tenant->setSmtpUsername(trim((string) $request->request->get('smtpUsername', '')) ?: null); $tenant->setSmtpFrom($smtpFrom); $password = $request->request->get('smtpPassword'); if (is_string($password) && $password !== '') { $tenant->setSmtpPasswordEncrypted(base64_encode(sodium_crypto_secretbox($password, $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), hash('sha256', $this->appSecret, true))) . ':' . base64_encode($nonce)); } }
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
        return new JsonResponse(['branding' => $this->serialize($tenant, $request, true)]);
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

    /** @return array{logoUrl:?string,squareLogoUrl:?string,faviconUrl:?string,initialPoints:int} */
    private function serialize(Tenant $tenant, Request $request, bool $includeSmtp = false): array
    {
        $origin = $request->getSchemeAndHttpHost();
        $branding = ['logoUrl' => $tenant->getLogoPath() ? $origin.$tenant->getLogoPath() : null, 'squareLogoUrl' => $tenant->getSquareLogoPath() ? $origin.$tenant->getSquareLogoPath() : null, 'faviconUrl' => $tenant->getFaviconPath() ? $origin.$tenant->getFaviconPath() : null, 'initialPoints' => $tenant->getInitialPoints(), 'birthdayBonusPoints' => $tenant->getBirthdayBonusPoints(), 'pointsPerEuro' => $tenant->getPointsPerEuro(), 'allowDuplicateReceiptImports' => $tenant->allowsDuplicateReceiptImports(), 'showCustomerDebugOutput' => $tenant->showsCustomerDebugOutput()];
        if ($includeSmtp) { $branding['receiptQrPrefix'] = $tenant->getReceiptQrPrefix(); }
        if ($includeSmtp) { $branding += ['smtpHost' => $tenant->getSmtpHost(), 'smtpPort' => $tenant->getSmtpPort(), 'smtpEncryption' => $tenant->getSmtpEncryption(), 'smtpUsername' => $tenant->getSmtpUsername(), 'smtpFrom' => $tenant->getSmtpFrom(), 'smtpPasswordConfigured' => $tenant->getSmtpPasswordEncrypted() !== null]; }
        return $branding;
    }
}
