<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppointmentBlock;
use App\Entity\AppointmentType;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAppointmentBlockController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
    ) {}

    #[Route('/api/v1/appointments/blocks', methods: ['GET'])]
    public function customerBlocks(Request $request): JsonResponse
    {
        $type = $this->typeFor((int) $request->query->get('typeId'));
        if ($type === null) {
            return new JsonResponse(['blocks' => []]);
        }

        $blocks = $this->entityManager->createQueryBuilder()
            ->select('block')
            ->from(AppointmentBlock::class, 'block')
            ->where('block.tenant = :tenant')
            ->andWhere('(block.type IS NULL OR block.type = :type)')
            ->andWhere('block.endsOn >= :today')
            ->setParameter('tenant', $this->tenant->get())
            ->setParameter('type', $type)
            ->setParameter('today', (new \DateTimeImmutable())->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        return new JsonResponse(['blocks' => array_map($this->serialize(...), $blocks)]);
    }

    #[Route('/api/v1/admin/appointments/blocks', methods: ['GET', 'POST'])]
    public function blocks(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        if ($request->isMethod('GET')) {
            $blocks = $this->entityManager->getRepository(AppointmentBlock::class)->findBy(
                ['tenant' => $this->tenant->get()],
                ['startsOn' => 'ASC'],
            );
            $today = (new \DateTimeImmutable())->format('Y-m-d');
            return new JsonResponse(['blocks' => array_values(array_map(fn (AppointmentBlock $block) => $this->serialize($block), array_filter($blocks, fn (AppointmentBlock $block) => $block->getEndsOn() >= $today)))]);
        }

        $data = $request->toArray();
        $values = $this->values($data);
        if ($values === null) {
            return new JsonResponse(['message' => 'Ungültige Sperrzeit.'], 422);
        }

        [$type, $startsOn, $endsOn, $startsAt, $endsAt, $comment] = $values;
        $block = new AppointmentBlock($this->tenant->get(), $type, $startsOn, $endsOn, $startsAt, $endsAt, $comment);
        $this->entityManager->persist($block);
        $this->entityManager->flush();

        return new JsonResponse(['block' => $this->serialize($block)], 201);
    }

    #[Route('/api/v1/admin/appointments/blocks/{id}', methods: ['PATCH', 'DELETE'])]
    public function block(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $block = $this->entityManager->getRepository(AppointmentBlock::class)->findOneBy(['id' => $id, 'tenant' => $this->tenant->get()]);
        if (!$block instanceof AppointmentBlock) {
            return new JsonResponse(['message' => 'Nicht gefunden.'], 404);
        }

        if ($request->isMethod('DELETE')) {
            $this->entityManager->remove($block);
            $this->entityManager->flush();
            return new JsonResponse(status: 204);
        }

        $values = $this->values($request->toArray());
        if ($values === null) {
            return new JsonResponse(['message' => 'Ungültige Sperrzeit.'], 422);
        }

        $block->update(...$values);
        $this->entityManager->flush();
        return new JsonResponse(['block' => $this->serialize($block)]);
    }

    private function typeFor(int $id): ?AppointmentType
    {
        return $this->entityManager->getRepository(AppointmentType::class)->findOneBy(['id' => $id, 'tenant' => $this->tenant->get()]);
    }

    /** @return array{0: ?AppointmentType, 1: string, 2: string, 3: ?string, 4: ?string, 5: ?string}|null */
    private function values(array $data): ?array
    {
        $startsOn = (string) ($data['startsOn'] ?? '');
        $endsOn = (string) ($data['endsOn'] ?? '');
        $hasTime = (bool) ($data['hasTime'] ?? false);
        $startsAt = $hasTime ? (string) ($data['startsAt'] ?? '') : null;
        $endsAt = $hasTime ? (string) ($data['endsAt'] ?? '') : null;
        $typeId = (int) ($data['typeId'] ?? 0);
        $type = $typeId > 0 ? $this->typeFor($typeId) : null;

        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startsOn) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endsOn) || $startsOn > $endsOn || ($hasTime && (!preg_match('/^\\d{2}:\\d{2}$/', $startsAt) || !preg_match('/^\\d{2}:\\d{2}$/', $endsAt) || $startsAt >= $endsAt)) || ($typeId > 0 && $type === null)) {
            return null;
        }

        $comment = trim((string) ($data['comment'] ?? ''));
        return [$type, $startsOn, $endsOn, $startsAt, $endsAt, $comment === '' ? null : mb_substr($comment, 0, 500)];
    }

    private function serialize(AppointmentBlock $block): array
    {
        return ['id' => $block->getId(), 'typeId' => $block->getType()?->getId(), 'startsOn' => $block->getStartsOn(), 'endsOn' => $block->getEndsOn(), 'startsAt' => $block->getStartsAt(), 'endsAt' => $block->getEndsAt(), 'comment' => $block->getComment()];
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && [] !== array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles());
    }
}
