<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{FamilyConnection, Medication, User};
use App\Repository\TenantMembershipRepository;
use App\Service\{ActiveTenantProvider, ChatCipher};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{File\UploadedFile, JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApiMedicationController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatCipher $cipher,
    ) {
    }

    #[Route('/api/v1/medications', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $currentUser = $this->user();
        $ownerId = null;
        // A known ID alone is never enough: family access is resolved below.
        $requestedOwnerId = $request->query->get('ownerId');
        if (is_string($requestedOwnerId) && ctype_digit($requestedOwnerId)) $ownerId = (int) $requestedOwnerId;
        [$user, $canManage] = $this->owner($currentUser, $ownerId);
        $medications = $this->entityManager->getRepository(Medication::class)->findBy([
            'tenant' => $this->tenant->get(), 'user' => $user,
        ], ['updatedAt' => 'DESC']);

        return new JsonResponse(['medications' => array_map($this->serialize(...), $medications), 'access' => ['ownerId' => $user->getId(), 'canManage' => $canManage]], Response::HTTP_OK, $this->privateHeaders());
    }

    #[Route('/api/v1/medications', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $medication = new Medication($this->tenant->get(), $this->user());
        $this->entityManager->wrapInTransaction(function () use ($medication, $request): void {
            $this->entityManager->persist($medication);
            $this->entityManager->flush();
            $this->apply($medication, $request);
            $this->entityManager->flush();
        });

        return new JsonResponse(['medication' => $this->serialize($medication)], Response::HTTP_CREATED, $this->privateHeaders());
    }

    #[Route('/api/v1/medications/{id}', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $medication = $this->medication($id, true);
        $this->apply($medication, $request);
        $this->entityManager->flush();

        return new JsonResponse(['medication' => $this->serialize($medication)], Response::HTTP_OK, $this->privateHeaders());
    }

    #[Route('/api/v1/medications/{id}', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $this->entityManager->remove($this->medication($id, true));
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT, $this->privateHeaders());
    }

    #[Route('/api/v1/medications/{id}/image', methods: ['GET'])]
    public function image(int $id): Response
    {
        $medication = $this->medication($id);
        if ($medication->encryptedImage === null) {
            throw new HttpException(Response::HTTP_NOT_FOUND);
        }

        return new Response($this->cipher->decrypt($medication->encryptedImage, $this->context($medication, 'image')), Response::HTTP_OK, [
            ...$this->privateHeaders(), 'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="medikament.jpg"',
        ]);
    }

    private function apply(Medication $medication, Request $request): void
    {
        $name = $this->text($request->request->get('name'), 2, 160, false);
        $dosage = $this->text($request->request->get('dosage'), 0, 500, true);
        $schedule = $this->schedule($request->request->get('schedule'));
        $notes = $this->text($request->request->get('notes'), 0, 2000, true);
        $refillDate = $this->date($request->request->get('refillDate'));
        if ($name === false || $dosage === false || $schedule === false || $notes === false || $refillDate === false) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Bitte prüfen Sie Ihre Angaben.');
        }

        $medication->encryptedName = $this->cipher->encrypt($name, $this->context($medication, 'name'));
        $medication->encryptedDosage = $dosage === null ? null : $this->cipher->encrypt($dosage, $this->context($medication, 'dosage'));
        $medication->encryptedSchedule = $schedule === null ? null : $this->cipher->encrypt($schedule, $this->context($medication, 'schedule'));
        $medication->encryptedNotes = $notes === null ? null : $this->cipher->encrypt($notes, $this->context($medication, 'notes'));
        $medication->refillDate = $refillDate;
        $image = $request->files->get('image');
        if ($image instanceof UploadedFile) {
            $medication->encryptedImage = $this->cipher->encrypt($this->imageBytes($image), $this->context($medication, 'image'));
        }
        $medication->updatedAt = new \DateTimeImmutable();
    }

    private function medication(int $id, bool $manage = false): Medication
    {
        $medication = $this->entityManager->getRepository(Medication::class)->find($id);
        $user = $this->user();
        if (!$medication instanceof Medication || $medication->tenant->getId() !== $this->tenant->get()->getId() || ($medication->user->getId() !== $user->getId() && !$this->familyAccess($medication->user, $user, $manage))) {
            throw new HttpException(Response::HTTP_NOT_FOUND);
        }
        return $medication;
    }

    /** @return array{0:User,1:bool} */
    private function owner(User $current, ?int $ownerId): array
    {
        if ($ownerId === null || $ownerId === $current->getId()) return [$current, true];
        $owner = $this->entityManager->getRepository(User::class)->find($ownerId);
        if (!$owner instanceof User || !$this->familyAccess($owner, $current, false)) throw new HttpException(Response::HTTP_NOT_FOUND);
        return [$owner, $this->familyAccess($owner, $current, true)];
    }

    private function familyAccess(User $owner, User $grantee, bool $manage): bool
    {
        $connection = $this->entityManager->getRepository(FamilyConnection::class)->createQueryBuilder('connection')
            ->where('connection.tenant = :tenant')->andWhere('connection.status = :status')
            ->andWhere('(connection.participantOne = :owner AND connection.participantTwo = :grantee) OR (connection.participantOne = :grantee AND connection.participantTwo = :owner)')
            ->setParameter('tenant', $this->tenant->get())->setParameter('status', 'accepted')->setParameter('owner', $owner)->setParameter('grantee', $grantee)->setMaxResults(1)->getQuery()->getOneOrNullResult();
        return $connection instanceof FamilyConnection && ($manage ? $connection->canManageMedication($owner, $grantee) : $connection->canViewMedication($owner, $grantee));
    }

    private function user(): User
    {
        $user = $this->security->getUser();
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $this->tenant->get()) : null;
        if (!$user instanceof User || $membership === null || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }
        return $user;
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache'];
    }

    /** @return array<string, bool|int|string|null> */
    private function serialize(Medication $medication): array
    {
        return [
            'id' => $medication->id,
            'name' => $this->cipher->decrypt($medication->encryptedName, $this->context($medication, 'name')),
            'dosage' => $medication->encryptedDosage === null ? null : $this->cipher->decrypt($medication->encryptedDosage, $this->context($medication, 'dosage')),
            'schedule' => $medication->encryptedSchedule === null ? null : $this->cipher->decrypt($medication->encryptedSchedule, $this->context($medication, 'schedule')),
            'notes' => $medication->encryptedNotes === null ? null : $this->cipher->decrypt($medication->encryptedNotes, $this->context($medication, 'notes')),
            'refillDate' => $medication->refillDate?->format('Y-m-d'),
            'hasImage' => $medication->encryptedImage !== null,
            'updatedAt' => $medication->updatedAt->format(DATE_ATOM),
        ];
    }

    private function context(Medication $medication, string $field): string
    {
        return 'medication:' . $medication->tenant->getId() . ':' . $medication->user->getId() . ':' . $medication->id . ':' . $field;
    }

    private function text(mixed $value, int $min, int $max, bool $nullable): string|null|false
    {
        if (!is_string($value)) return $nullable && $value === null ? null : false;
        $value = trim($value);
        if ($value === '' && $nullable) return null;
        return mb_strlen($value) >= $min && mb_strlen($value) <= $max ? $value : false;
    }

    private function date(mixed $value): \DateTimeImmutable|false|null
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : false;
    }

    private function schedule(mixed $value): string|false
    {
        if (!is_string($value)) return false;
        $parts = explode('-', trim($value));
        if (count($parts) !== 4) return false;

        $normalized = [];
        foreach ($parts as $part) {
            $part = str_replace(',', '.', trim($part));
            if (!preg_match('/^(?:0|[1-9][0-9]?)(?:\.[0-9]{1,2})?$/D', $part)) return false;
            $normalized[] = str_contains($part, '.') ? rtrim(rtrim($part, '0'), '.') : $part;
        }

        return implode('-', $normalized);
    }

    private function imageBytes(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024 || !in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Erlaubt sind JPEG, PNG und WebP bis 5 MB.');
        $size = @getimagesize($file->getPathname());
        if (!$size || $size[0] * $size[1] > 16000000) throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Das Bild darf maximal 16 Megapixel haben.');
        $source = @imagecreatefromstring((string) file_get_contents($file->getPathname()));
        if (!$source) throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Das Bild kann nicht gelesen werden.');
        $scale = min(1, 2000 / max($size[0], $size[1])); $width = max(1, (int) round($size[0] * $scale)); $height = max(1, (int) round($size[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height); imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start(); imagejpeg($canvas, null, 90); $bytes = ob_get_clean(); imagedestroy($source); imagedestroy($canvas);
        if (!is_string($bytes)) throw new \RuntimeException('Image processing failed.');
        return $bytes;
    }
}
