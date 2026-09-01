<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiProfileController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/profile', name: 'api_v1_profile_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        $user = $this->currentTenantUser();
        return $user instanceof User
            ? new JsonResponse(['profile' => $this->serialize($user, $request)])
            : new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
    }

    #[Route('/api/v1/profile', name: 'api_v1_profile_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->currentTenantUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->invalidProfile();
        }

        $displayName = $this->text($data['displayName'] ?? null, 2, 160, false);
        $phone = $this->text($data['phone'] ?? null, 0, 40, true);
        $streetAddress = $this->text($data['streetAddress'] ?? null, 0, 160, true);
        $postalCode = $this->text($data['postalCode'] ?? null, 0, 20, true);
        $city = $this->text($data['city'] ?? null, 0, 120, true);

        if (!is_string($displayName) || $phone === false || $streetAddress === false || $postalCode === false || $city === false) {
            return $this->invalidProfile();
        }

        $user->setDisplayName($displayName);
        $user->setPhone($phone);
        $user->setStreetAddress($streetAddress);
        $user->setPostalCode($postalCode);
        $user->setCity($city);
        $this->entityManager->flush();

        return new JsonResponse(['profile' => $this->serialize($user, $request)]);
    }

    #[Route('/api/v1/profile/photo', name: 'api_v1_profile_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $this->currentTenantUser();
        $photo = $request->files->get('photo');
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        if (!$photo instanceof UploadedFile || $photo->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['message' => 'Bitte wählen Sie ein Bild bis maximal 5 MB.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$photo->getMimeType()] ?? null;
        if ($extension === null) {
            return new JsonResponse(['message' => 'Erlaubt sind JPEG-, PNG- oder WebP-Bilder.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $directory = dirname(__DIR__, 2).'/public/uploads/profiles';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return new JsonResponse(['message' => 'Das Profilbild konnte nicht gespeichert werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $filename = 'profile-'.$user->getId().'-'.bin2hex(random_bytes(8)).'.'.$extension;
        $photo->move($directory, $filename);
        $previousPath = $user->getProfileImagePath();
        $user->setProfileImagePath('/uploads/profiles/'.$filename);
        $this->entityManager->flush();

        if ($previousPath !== null) {
            $previousFile = dirname(__DIR__, 2).'/public'.$previousPath;
            if (is_file($previousFile)) {
                unlink($previousFile);
            }
        }

        return new JsonResponse(['profile' => $this->serialize($user, $request)]);
    }

    private function currentTenantUser(): ?User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->memberships->hasActiveMembershipFor($user, $this->activeTenant->get())) {
            return null;
        }
        return $user;
    }

    private function text(mixed $value, int $minimumLength, int $maximumLength, bool $nullable): string|false|null
    {
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '') {
            return $nullable ? null : false;
        }
        return mb_strlen($value) >= $minimumLength && mb_strlen($value) <= $maximumLength ? $value : false;
    }

    /** @return array{id:int,username:string,email:string,displayName:string,phone:?string,streetAddress:?string,postalCode:?string,city:?string,profileImageUrl:?string} */
    private function serialize(User $user, Request $request): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'phone' => $user->getPhone(),
            'streetAddress' => $user->getStreetAddress(),
            'postalCode' => $user->getPostalCode(),
            'city' => $user->getCity(),
            'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath(),
        ];
    }

    private function invalidProfile(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte prüfen Sie Ihre Kontaktdaten.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
