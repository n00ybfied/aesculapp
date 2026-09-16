<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\RichTextSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiContactController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];
    /** @var list<string> */
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private readonly ActiveTenantProvider $activeTenant, private readonly TenantMembershipRepository $memberships, private readonly Security $security, private readonly EntityManagerInterface $entityManager, private readonly RichTextSanitizer $richText) {}

    #[Route('/api/v1/contact', name: 'api_v1_contact', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse(['contact' => $this->serialize($this->activeTenant->get())]);
    }

    #[Route('/api/v1/admin/settings/contact', name: 'api_v1_admin_contact', methods: ['GET'])]
    public function adminGet(): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        return new JsonResponse(['contact' => $this->serialize($this->activeTenant->get())]);
    }

    #[Route('/api/v1/admin/settings/contact', name: 'api_v1_admin_contact_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        try { $input = $request->toArray(); } catch (\JsonException) { return new JsonResponse(['message' => 'Ungültige Kontaktdaten.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
        $tenant = $this->activeTenant->get();
        $address = $this->text($input['address'] ?? null, 255);
        $phone = $this->text($input['phone'] ?? null, 80);
        $email = $this->text($input['email'] ?? null, 255);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) return new JsonResponse(['message' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.'], 422);
        $openingHours = $this->openingHours($input['openingHours'] ?? null);
        if ($openingHours === false) return new JsonResponse(['message' => 'Bitte prüfen Sie die Öffnungszeiten.'], 422);
        $latitude = $this->coordinate($input['latitude'] ?? null, -90, 90);
        $longitude = $this->coordinate($input['longitude'] ?? null, -180, 180);
        $zoom = $this->zoom($input['mapZoom'] ?? null);
        $googleMapsUrl = $this->text($input['googleMapsUrl'] ?? null, 2048);
        if ($googleMapsUrl !== null && (filter_var($googleMapsUrl, FILTER_VALIDATE_URL) === false || parse_url($googleMapsUrl, PHP_URL_SCHEME) !== 'https')) return new JsonResponse(['message' => 'Bitte hinterlegen Sie eine vollständige HTTPS-Adresse für Google Maps.'], 422);
        if ($latitude === false || $longitude === false || $zoom === false) return new JsonResponse(['message' => 'Bitte prüfen Sie Kartenkoordinaten und Zoomstufe.'], 422);
        // A half-filled location is not useful to the customer, but it must not block saving the other contact details.
        if (($latitude === null) !== ($longitude === null)) { $latitude = null; $longitude = null; }
        $additionalHtml = $input['additionalHtml'] ?? '';
        if (!is_string($additionalHtml) || mb_strlen($additionalHtml) > 100000) return new JsonResponse(['message' => 'Der Zusatztext ist ungültig.'], 422);
        $tenant->setContactAddress($address); $tenant->setContactPhone($phone); $tenant->setContactEmail($email);
        $tenant->setContactOpeningHours($openingHours); $tenant->setContactLatitude($latitude); $tenant->setContactLongitude($longitude); $tenant->setContactMapZoom($zoom); $tenant->setContactGoogleMapsUrl($googleMapsUrl); $tenant->setContactAdditionalHtml($this->richText->sanitize($additionalHtml));
        $this->entityManager->flush();
        return new JsonResponse(['contact' => $this->serialize($tenant)]);
    }

    private function isAdmin(): bool { $user = $this->security->getUser(); if (!$user instanceof User) return false; $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get()); return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles()); }
    private function text(mixed $value, int $maxLength): ?string { if (!is_string($value)) return null; $value = trim($value); return $value === '' ? null : mb_substr($value, 0, $maxLength); }
    /** @return array<string, string>|false */
    private function openingHours(mixed $value): array|false { if (!is_array($value)) return array_fill_keys(self::DAYS, ''); $result = []; foreach (self::DAYS as $day) { $hour = $value[$day] ?? ''; if (!is_string($hour) || mb_strlen($hour) > 120) return false; $result[$day] = trim($hour); } return $result; }
    private function coordinate(mixed $value, float $min, float $max): float|false|null { if ($value === null || $value === '') return null; if (!is_numeric($value)) return false; $coordinate = (float) $value; return $coordinate >= $min && $coordinate <= $max ? $coordinate : false; }
    private function zoom(mixed $value): int|false|null { if ($value === null || $value === '') return null; if (filter_var($value, FILTER_VALIDATE_INT) === false) return false; $zoom = (int) $value; return $zoom >= 1 && $zoom <= 20 ? $zoom : false; }
    /** @return array<string, mixed> */
    private function serialize(Tenant $tenant): array { return ['address' => $tenant->getContactAddress(), 'phone' => $tenant->getContactPhone(), 'email' => $tenant->getContactEmail(), 'openingHours' => $tenant->getContactOpeningHours() ?? array_fill_keys(self::DAYS, ''), 'latitude' => $tenant->getContactLatitude(), 'longitude' => $tenant->getContactLongitude(), 'mapZoom' => $tenant->getContactMapZoom() ?? 15, 'googleMapsUrl' => $tenant->getContactGoogleMapsUrl(), 'additionalHtml' => $this->richText->sanitize($tenant->getContactAdditionalHtml() ?? '')]; }
}
