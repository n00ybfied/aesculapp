<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{User, TenantMembership};
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\ImageProcessor;
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
        private readonly ImageProcessor $imageProcessor,
    ) {
    }

    #[Route('/api/v1/profile', name: 'api_v1_profile_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        return $membership instanceof TenantMembership
            ? new JsonResponse(['profile' => $this->serialize($membership->getUser(), $membership, $request)])
            : new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
    }

    #[Route('/api/v1/profile', name: 'api_v1_profile_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        if (!$membership instanceof TenantMembership) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $user = $membership->getUser();

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
        $birthDate = $this->birthDate($data['birthDate'] ?? null);
        $newsletterEnabled = $this->boolean($data['newsletterEnabled'] ?? null);
        $chatPushEnabled = $this->boolean($data['chatPushEnabled'] ?? null);
        $rewardPushEnabled = $this->boolean($data['rewardPushEnabled'] ?? null);
        $newsPushEnabled = $this->boolean($data['newsPushEnabled'] ?? null);

        if (!is_string($displayName) || $phone === false || $streetAddress === false || $postalCode === false || $city === false || $birthDate === false || $newsletterEnabled === null || $chatPushEnabled === null || $rewardPushEnabled === null || $newsPushEnabled === null) {
            return $this->invalidProfile();
        }

        $user->setDisplayName($displayName);
        $user->setPhone($phone);
        $user->setStreetAddress($streetAddress);
        $user->setPostalCode($postalCode);
        $user->setCity($city);
        $user->setBirthDate($birthDate);
        $membership->setNewsletterEnabled($newsletterEnabled);
        $membership->setChatPushEnabled($chatPushEnabled);
        $membership->setRewardPushEnabled($rewardPushEnabled);
        $membership->setNewsPushEnabled($newsPushEnabled);
        $this->entityManager->flush();

        return new JsonResponse(['profile' => $this->serialize($user, $membership, $request)]);
    }

    #[Route('/api/v1/profile/photo', name: 'api_v1_profile_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        $user = $membership?->getUser();
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

        $filename = 'profile-'.$user->getId().'-'.bin2hex(random_bytes(8)).'.jpg';
        if (!$this->imageProcessor->saveProfileJpeg($photo, $directory.'/'.$filename)) {
            return new JsonResponse(['message' => 'Das Profilbild konnte nicht verarbeitet werden.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $previousPath = $user->getProfileImagePath();
        $user->setProfileImagePath('/uploads/profiles/'.$filename);
        $this->entityManager->flush();

        if ($previousPath !== null) {
            $previousFile = dirname(__DIR__, 2).'/public'.$previousPath;
            if (is_file($previousFile)) {
                unlink($previousFile);
            }
        }

        return new JsonResponse(['profile' => $this->serialize($user, $membership, $request)]);
    }

    private function currentTenantMembership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }
        return $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
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

    private function birthDate(mixed $value): \DateTimeImmutable|false|null { if ($value === null || $value === '') { return null; } if (!is_string($value)) { return false; } try { $date = new \DateTimeImmutable($value); return $date > new \DateTimeImmutable('-14 years') || $date < new \DateTimeImmutable('-120 years') ? false : $date; } catch (\Exception) { return false; } }
    private function boolean(mixed $value): ?bool { return is_bool($value) ? $value : null; }

    /** @return array{id:int,username:string,email:string,displayName:string,phone:?string,streetAddress:?string,postalCode:?string,city:?string,profileImageUrl:?string} */
    private function serialize(User $user, TenantMembership $membership, Request $request): array
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
            'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
            'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath(),
            'newsletterEnabled' => $membership->isNewsletterEnabled(),
            'chatPushEnabled' => $membership->isChatPushEnabled(),
            'rewardPushEnabled' => $membership->isRewardPushEnabled(),
            'newsPushEnabled' => $membership->isNewsPushEnabled(),
        ];
    }

    private function invalidProfile(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte prüfen Sie Ihre Kontaktdaten.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
