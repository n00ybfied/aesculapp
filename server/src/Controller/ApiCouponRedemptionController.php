<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coupon;
use App\Entity\CouponRedemption;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiCouponRedemptionController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
    ) {}

    #[Route('/api/v1/coupons/redeem', methods: ['POST'])]
    public function redeem(Request $request): JsonResponse
    {
        $user = $this->customer();
        if ($user === null) return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);

        try { $ids = $request->toArray()['couponIds'] ?? null; }
        catch (\Throwable) { $ids = null; }
        if (!is_array($ids) || $ids === [] || count($ids) > 50) {
            return new JsonResponse(['message' => 'Bitte wählen Sie mindestens einen Gutschein aus.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) return new JsonResponse(['message' => 'Ungültige Gutscheinauswahl.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return new JsonResponse(['message' => 'Ein Gutschein darf nur einmal ausgewählt werden.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->em->wrapInTransaction(function () use ($ids, $user, $request): JsonResponse {
            // Customer locking serializes parallel submissions of any coupon basket.
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            $tenant = $this->tenant->get();
            $previous = $this->em->getRepository(CouponRedemption::class)->findBy([
                'tenant' => $tenant, 'customer' => $user, 'status' => 'active',
            ]);
            foreach ($previous as $item) {
                if ($item->isActive()) return new JsonResponse(['message' => 'Bitte zeigen Sie zuerst Ihre aktive Gutschein-Einlösung vor.'], Response::HTTP_CONFLICT);
            }
            $usedIds = [];
            foreach ($previous as $item) $usedIds[$item->getCoupon()->getId()] = true;

            $now = new \DateTimeImmutable();
            $coupons = [];
            foreach ($ids as $id) {
                $coupon = $this->em->getRepository(Coupon::class)->find($id);
                if (!$coupon instanceof Coupon || $coupon->getTenant() !== $tenant || !$coupon->isVisible() || !$coupon->isCurrentlyAvailable($now)) {
                    return new JsonResponse(['message' => 'Ein ausgewählter Gutschein ist nicht verfügbar.'], Response::HTTP_NOT_FOUND);
                }
                if (isset($usedIds[$id])) return new JsonResponse(['message' => 'Ein ausgewählter Gutschein wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
                $coupons[] = $coupon;
            }

            $batchId = bin2hex(random_bytes(16));
            $items = [];
            foreach ($coupons as $coupon) {
                $item = new CouponRedemption($tenant, $coupon, $user, $batchId, $now);
                $this->em->persist($item);
                $items[] = $item;
            }
            $this->em->flush();
            return new JsonResponse(['redemption' => $this->serializeBatch($items, $request)], Response::HTTP_CREATED);
        });
    }

    #[Route('/api/v1/coupons/redemptions/active', methods: ['GET'])]
    public function customerActive(Request $request): JsonResponse
    {
        $user = $this->customer();
        if ($user === null) return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        $items = $this->em->getRepository(CouponRedemption::class)->findBy([
            'tenant' => $this->tenant->get(), 'customer' => $user, 'status' => 'active',
        ], ['id' => 'DESC']);
        foreach ($items as $item) {
            if ($item->isActive()) {
                $batch = array_values(array_filter($items, fn (CouponRedemption $part): bool => $part->getBatchId() === $item->getBatchId()));
                return new JsonResponse(['redemption' => $this->serializeBatch($batch, $request)]);
            }
        }
        return new JsonResponse(['redemption' => null]);
    }

    #[Route('/api/v1/admin/coupons/redemptions/active', methods: ['GET'])]
    public function adminActive(Request $request): JsonResponse
    {
        if (!$this->admin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $items = $this->em->getRepository(CouponRedemption::class)->findBy([
            'tenant' => $this->tenant->get(), 'status' => 'active',
        ], ['id' => 'DESC']);
        $batches = [];
        foreach ($items as $item) {
            if ($item->isActive()) $batches[$item->getBatchId()][] = $item;
        }
        return new JsonResponse(['redemptions' => array_map(
            fn (array $batch): array => $this->serializeBatch($batch, $request, true),
            array_values($batches),
        )]);
    }

    #[Route('/api/v1/admin/coupons/redemptions/{id}/cancel', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function cancel(string $id): Response
    {
        if (!$this->admin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        return $this->em->wrapInTransaction(function () use ($id): Response {
            $tenant = $this->tenant->get();
            $first = $this->em->getRepository(CouponRedemption::class)->findOneBy(['batchId' => $id, 'tenant' => $tenant]);
            if (!$first instanceof CouponRedemption) return new JsonResponse(['message' => 'Aktive Einlösung nicht gefunden.'], Response::HTTP_NOT_FOUND);
            $this->em->lock($first->getCustomer(), LockMode::PESSIMISTIC_WRITE);
            $items = $this->em->getRepository(CouponRedemption::class)->findBy(['batchId' => $id, 'tenant' => $tenant]);
            if ($items === [] || !$items[0]->isActive()) return new JsonResponse(['message' => 'Aktive Einlösung nicht gefunden.'], Response::HTTP_NOT_FOUND);
            foreach ($items as $item) $item->cancel();
            $this->em->flush();
            return new Response(status: Response::HTTP_NO_CONTENT);
        });
    }

    private function customer(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User && $this->memberships->hasActiveMembershipFor($user, $this->tenant->get()) ? $user : null;
    }

    private function admin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && [] !== array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles());
    }

    /** @param non-empty-list<CouponRedemption> $batch */
    private function serializeBatch(array $batch, Request $request, bool $withCustomer = false): array
    {
        $first = $batch[0];
        $user = $first->getCustomer();
        $items = array_map(function (CouponRedemption $entry) use ($request): array {
            $coupon = $entry->getCoupon();
            return [
                'couponId' => $coupon->getId(),
                'title' => $coupon->getTitle(),
                'subtitle' => $coupon->getSubtitle(),
                'imageUrl' => $coupon->getImagePath() === '' ? null : $request->getSchemeAndHttpHost().$coupon->getImagePath(),
            ];
        }, $batch);
        $result = [
            'id' => $first->getBatchId(),
            'items' => $items,
            'summary' => implode(', ', array_column($items, 'title')),
            'validUntil' => $first->getValidUntil()->format(DATE_ATOM),
        ];
        if ($withCustomer) {
            $result['customer'] = $user->getDisplayName();
            $result['customerDetails'] = [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'username' => $user->getUsername(),
                'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath(),
            ];
        }
        return $result;
    }
}
