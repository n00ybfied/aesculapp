<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{User, TenantMembership, NewsCategory};
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\ChatPushService;
use App\Service\ImageProcessor;
use App\Service\ProfileCompletionBonusService;
use App\Service\UsernameReservation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiProfileController
{
    /** @var list<string> */
    private const FOOTER_NAVIGATION_ITEMS = ['home', 'chat', 'rewards', 'coupons', 'news', 'appointments', 'my-appointments', 'medications', 'family', 'contact', 'website', 'achievements'];

    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageProcessor $imageProcessor,
        private readonly ChatPushService $push,
        private readonly UsernameReservation $usernameReservation,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RevokeRefreshTokenManagerInterface $refreshTokens,
        private readonly ProfileCompletionBonusService $profileBonus,
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

    #[Route('/api/v1/profile/setup', name: 'api_v1_profile_setup', methods: ['POST'])]
    public function completeSetup(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        if (!$membership instanceof TenantMembership) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        if ($membership->isCustomerSetupCompleted()) return new JsonResponse(['message' => 'Die Einrichtung ist bereits abgeschlossen.'], Response::HTTP_CONFLICT);

        try { $data = $request->toArray(); } catch (JsonException) { return $this->invalidProfile(); }
        $firstName = $this->text($data['firstName'] ?? '', 0, 80, true);
        $lastName = $this->text($data['lastName'] ?? '', 0, 80, true);
        $salutation = $data['salutation'] ?? null;
        $phone = $this->text($data['phone'] ?? '', 0, 40, true);
        $streetAddress = $this->text($data['streetAddress'] ?? '', 0, 160, true);
        $postalCode = $this->text($data['postalCode'] ?? '', 0, 20, true);
        $city = $this->text($data['city'] ?? '', 0, 120, true);
        $birthDate = $this->birthDate($data['birthDate'] ?? null);
        $categoryIds = $data['newsCategoryIds'] ?? null;
        $preferences = [];
        foreach (['newsletterEnabled', 'chatPushEnabled', 'rewardPushEnabled', 'newsPushEnabled', 'medicationPushEnabled', 'appointmentPushEnabled', 'familyPushEnabled'] as $key) {
            $preferences[$key] = $this->boolean($data[$key] ?? null);
        }
        if ($firstName === false || $lastName === false || mb_strlen(($firstName ?? '').' '.($lastName ?? '')) > 160
            || !in_array($salutation, [null, 'frau', 'herr', 'divers'], true)
            || $phone === false || $streetAddress === false || $postalCode === false || $city === false || $birthDate === false
            || !is_array($categoryIds) || !array_is_list($categoryIds) || count($categoryIds) > 100
            || in_array(null, $preferences, true)) return $this->invalidProfile();

        foreach ($categoryIds as $categoryId) {
            if (!is_int($categoryId) || $categoryId < 1 || !$this->entityManager->getRepository(NewsCategory::class)->findOneBy(['id' => $categoryId, 'tenant' => $this->activeTenant->get()])) return $this->invalidProfile();
        }

        $user = $membership->getUser();
        $user->setNames($firstName, $lastName);
        $user->setSalutation($salutation);
        $user->setPhone($phone);
        $user->setStreetAddress($streetAddress);
        $user->setPostalCode($postalCode);
        $user->setCity($city);
        $user->setBirthDate($birthDate);
        $membership->setNewsCategoryIds($categoryIds);
        $membership->setNewsletterEnabled($preferences['newsletterEnabled']);
        $membership->setChatPushEnabled($preferences['chatPushEnabled']);
        $membership->setRewardPushEnabled($preferences['rewardPushEnabled']);
        $membership->setNewsPushEnabled($preferences['newsPushEnabled']);
        $membership->setMedicationPushEnabled($preferences['medicationPushEnabled']);
        $membership->setAppointmentPushEnabled($preferences['appointmentPushEnabled']);
        $membership->setFamilyPushEnabled($preferences['familyPushEnabled']);
        $membership->completeCustomerSetup();
        $this->entityManager->wrapInTransaction(fn (): int => $this->profileBonus->awardIfEligible($membership));
        return new JsonResponse(['profile' => $this->serialize($user, $membership, $request)]);
    }

    #[Route('/api/v1/profile/setup/skip', name: 'api_v1_profile_setup_skip', methods: ['POST'])]
    public function skipSetup(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        if (!$membership instanceof TenantMembership) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        if (!$membership->isCustomerSetupCompleted()) {
            $membership->completeCustomerSetup();
            $this->entityManager->wrapInTransaction(fn (): int => $this->profileBonus->awardIfEligible($membership));
        }
        return new JsonResponse(['profile' => $this->serialize($membership->getUser(), $membership, $request)]);
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

        $username = $data['username'] ?? $user->getUsername();
        $displayName = $this->text($data['displayName'] ?? $user->getDisplayName(), 2, 160, false);
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $phone = $this->text($data['phone'] ?? null, 0, 40, true);
        $streetAddress = $this->text($data['streetAddress'] ?? null, 0, 160, true);
        $postalCode = $this->text($data['postalCode'] ?? null, 0, 20, true);
        $city = $this->text($data['city'] ?? null, 0, 120, true);
        $birthDate = $this->birthDate($data['birthDate'] ?? null);
        $salutation = array_key_exists('salutation', $data) ? $data['salutation'] : $user->getSalutation();
        $newsCategoryIds = $data['newsCategoryIds'] ?? $membership->getNewsCategoryIds();
        $newsletterEnabled = $this->boolean($data['newsletterEnabled'] ?? null);
        $chatPushEnabled = $this->boolean($data['chatPushEnabled'] ?? null);
        $rewardPushEnabled = $this->boolean($data['rewardPushEnabled'] ?? null);
        $newsPushEnabled = $this->boolean($data['newsPushEnabled'] ?? null);
        $medicationPushEnabled = $this->boolean($data['medicationPushEnabled'] ?? null);
        $appointmentPushEnabled = $this->boolean($data['appointmentPushEnabled'] ?? null);
        $familyPushEnabled = $this->boolean($data['familyPushEnabled'] ?? null);
        $morningReminderTime = $this->time($data['morningReminderTime'] ?? null);
        $noonReminderTime = $this->time($data['noonReminderTime'] ?? null);
        $eveningReminderTime = $this->time($data['eveningReminderTime'] ?? null);
        $nightReminderTime = $this->time($data['nightReminderTime'] ?? null);
        $footerNavigationItems = $this->footerNavigationItems($data['footerNavigationItems'] ?? null);

        if (!is_string($username) || !is_string($displayName) || $phone === false || $streetAddress === false || $postalCode === false || $city === false || $birthDate === false || !in_array($salutation, [null, 'frau', 'herr', 'divers'], true) || !is_array($newsCategoryIds) || !array_is_list($newsCategoryIds) || count($newsCategoryIds) > 100 || $newsletterEnabled === null || $chatPushEnabled === null || $rewardPushEnabled === null || $newsPushEnabled === null || $medicationPushEnabled === null || $appointmentPushEnabled === null || $familyPushEnabled === null || $morningReminderTime === false || $noonReminderTime === false || $eveningReminderTime === false || $nightReminderTime === false || $footerNavigationItems === false) {
            return $this->invalidProfile();
        }
        if ($firstName !== null || $lastName !== null) {
            $firstName = $this->text($firstName, 0, 80, true);
            $lastName = $this->text($lastName, 0, 80, true);
            if ($firstName === false || $lastName === false || mb_strlen(($firstName ?? '').' '.($lastName ?? '')) > 160) return $this->invalidProfile();
        }
        foreach ($newsCategoryIds as $categoryId) {
            if (!is_int($categoryId) || $categoryId < 1 || !$this->entityManager->getRepository(NewsCategory::class)->findOneBy(['id' => $categoryId, 'tenant' => $this->activeTenant->get()])) return $this->invalidProfile();
        }

        $username = mb_strtolower(trim($username));
        $usernameChanged = $username !== $user->getUsername();
        if ($username !== $user->getUsername() && preg_match('/^[a-z0-9][a-z0-9._+%@-]{2,99}$/D', $username) !== 1) {
            return new JsonResponse(['message' => 'Der Benutzername muss 3 bis 100 erlaubte Zeichen enthalten.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($usernameChanged && (!is_string($data['usernamePassword'] ?? null) || !$this->passwordHasher->isPasswordValid($user, $data['usernamePassword']))) {
            return new JsonResponse(['message' => 'Bitte bestätigen Sie die Änderung mit Ihrem aktuellen Passwort.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (($existing instanceof User && $existing->getId() !== $user->getId()) || ($usernameChanged && $this->usernameReservation->isReserved($username))) {
            return new JsonResponse(['message' => 'Dieser Benutzername ist bereits vergeben.'], Response::HTTP_CONFLICT);
        }

        if ($usernameChanged) {
            $this->usernameReservation->reserve($user->getUsername());
            $this->refreshTokens->revokeAllForUser($user);
            $user->setUsername($username);
        }
        if (array_key_exists('firstName', $data) || array_key_exists('lastName', $data)) $user->setNames($firstName, $lastName);
        else $user->setDisplayName($displayName);
        $user->setPhone($phone);
        $user->setStreetAddress($streetAddress);
        $user->setPostalCode($postalCode);
        $user->setCity($city);
        $user->setBirthDate($birthDate);
        $user->setSalutation($salutation);
        $membership->setNewsletterEnabled($newsletterEnabled);
        $membership->setChatPushEnabled($chatPushEnabled);
        $membership->setRewardPushEnabled($rewardPushEnabled);
        $membership->setNewsPushEnabled($newsPushEnabled);
        $membership->setNewsCategoryIds($newsCategoryIds);
        $membership->setMedicationPushEnabled($medicationPushEnabled);
        $membership->setAppointmentPushEnabled($appointmentPushEnabled);
        $membership->setFamilyPushEnabled($familyPushEnabled);
        $membership->setMorningReminderTime($morningReminderTime);
        $membership->setNoonReminderTime($noonReminderTime);
        $membership->setEveningReminderTime($eveningReminderTime);
        $membership->setNightReminderTime($nightReminderTime);
        $membership->setFooterNavigationItems($footerNavigationItems);
        try {
            $this->entityManager->wrapInTransaction(fn (): int => $this->profileBonus->awardIfEligible($membership));
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['message' => 'Dieser Benutzername ist bereits vergeben.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['profile' => $this->serialize($user, $membership, $request)]);
    }

    #[Route('/api/v1/profile/push/test', name: 'api_v1_profile_push_test', methods: ['POST'])]
    public function sendPushTest(): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        if (!$membership instanceof TenantMembership) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $result = $this->push->sendTestNotification($membership->getUser(), $this->activeTenant->get());
        if (!$result['configured']) {
            return new JsonResponse(['message' => 'Push-Benachrichtigungen sind auf dem Server noch nicht eingerichtet.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        if ($result['subscriptions'] === 0) {
            return new JsonResponse(['message' => 'Für dieses Konto ist noch kein Gerät für Push-Benachrichtigungen registriert.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($result['delivered'] === 0) {
            return new JsonResponse(['message' => 'Die Test-Benachrichtigung konnte nicht zugestellt werden.'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['success' => true, 'subscriptions' => $result['subscriptions']]);
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

    #[Route('/api/v1/profile/photo', name: 'api_v1_profile_photo_delete', methods: ['DELETE'])]
    public function deletePhoto(Request $request): JsonResponse
    {
        $membership = $this->currentTenantMembership();
        $user = $membership?->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $previousPath = $user->getProfileImagePath();
        $user->setProfileImagePath(null);
        $this->entityManager->flush();

        if ($previousPath !== null && str_starts_with($previousPath, '/uploads/profiles/')) {
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
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && in_array('ROLE_CUSTOMER', $membership->getRoles(), true) ? $membership : null;
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
    private function time(mixed $value): string|false { return is_string($value) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1 ? $value : false; }

    /** @return list<string>|false */
    private function footerNavigationItems(mixed $value): array|false
    {
        if (!is_array($value) || count($value) > 4) return false;
        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || !in_array($item, self::FOOTER_NAVIGATION_ITEMS, true) || in_array($item, $items, true)) return false;
            $items[] = $item;
        }
        return $items;
    }

    /** @return array<string, int|string|bool|array<array-key, mixed>|null> */
    private function serialize(User $user, TenantMembership $membership, Request $request): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'setupCompleted' => $membership->isCustomerSetupCompleted(),
            'profileCompletionBonusPoints' => $this->profileBonus->awardedPoints($membership) ?? $membership->getTenant()->getProfileCompletionBonusPoints(),
            'profileCompletionBonusAwarded' => $membership->hasProfileCompletionBonus(),
            'profileComplete' => $this->profileBonus->isComplete($membership),
            'phone' => $user->getPhone(),
            'streetAddress' => $user->getStreetAddress(),
            'postalCode' => $user->getPostalCode(),
            'city' => $user->getCity(),
            'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
            'salutation' => $user->getSalutation(),
            'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath(),
            'newsletterEnabled' => $membership->isNewsletterEnabled(),
            'chatPushEnabled' => $membership->isChatPushEnabled(),
            'rewardPushEnabled' => $membership->isRewardPushEnabled(),
            'newsPushEnabled' => $membership->isNewsPushEnabled(),
            'newsCategoryIds' => $membership->getNewsCategoryIds(),
            'medicationPushEnabled' => $membership->isMedicationPushEnabled(),
            'appointmentPushEnabled' => $membership->isAppointmentPushEnabled(),
            'familyPushEnabled' => $membership->isFamilyPushEnabled(),
            'morningReminderTime' => $membership->getMorningReminderTime(),
            'noonReminderTime' => $membership->getNoonReminderTime(),
            'eveningReminderTime' => $membership->getEveningReminderTime(),
            'nightReminderTime' => $membership->getNightReminderTime(),
            'footerNavigationItems' => $membership->getFooterNavigationItems(),
        ];
    }

    private function invalidProfile(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte prüfen Sie Ihre Kontaktdaten.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
