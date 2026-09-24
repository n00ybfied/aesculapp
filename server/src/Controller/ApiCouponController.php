<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coupon;
use App\Entity\CouponRedemption;
use App\Entity\MediaAsset;
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

final class ApiCouponController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly RichTextSanitizer $richText,
    ) {
    }

    #[Route('/api/v1/coupons', methods: ['GET'])]
    public function customer(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $tenant = $this->activeTenant->get();
        if (!$user instanceof User || !$this->memberships->hasCustomerMembershipFor($user, $tenant)) {
            return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $redemptions = $this->entityManager->getRepository(CouponRedemption::class)->findBy([
            'tenant' => $tenant,
            'customer' => $user,
            'status' => 'active',
        ]);
        $redeemedIds = [];
        foreach ($redemptions as $redemption) {
            $redeemedIds[$redemption->getCoupon()->getId()] = true;
        }

        $now = new \DateTimeImmutable();
        $query = $this->entityManager->createQueryBuilder()
            ->select('coupon')
            ->from(Coupon::class, 'coupon')
            ->where('coupon.tenant = :tenant')
            ->andWhere('coupon.isVisible = true')
            ->setParameter('tenant', $tenant)
            ->orderBy('coupon.id', 'DESC');

        if ($redeemedIds !== []) {
            $query
                ->andWhere('(((coupon.availableFrom IS NULL OR coupon.availableFrom <= :now) AND (coupon.availableUntil IS NULL OR coupon.availableUntil >= :now)) OR coupon.id IN (:redeemedIds))')
                ->setParameter('redeemedIds', array_keys($redeemedIds));
        } else {
            $query
                ->andWhere('(coupon.availableFrom IS NULL OR coupon.availableFrom <= :now)')
                ->andWhere('(coupon.availableUntil IS NULL OR coupon.availableUntil >= :now)');
        }

        $items = $query->setParameter('now', $now)->getQuery()->getResult();

        return new JsonResponse([
            'coupons' => array_map(
                fn (Coupon $coupon) => [...$this->serialize($coupon, $request), 'isRedeemed' => isset($redeemedIds[$coupon->getId()])],
                $items,
            ),
        ]);
    }

    #[Route('/api/v1/admin/coupons', methods: ['GET'])]
    public function adminList(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $pageSize = $this->adminPageSize($request);
        $query = trim((string) $request->query->get('query', ''));
        $couponsQuery = $this->entityManager->createQueryBuilder()
            ->select('coupon')
            ->from(Coupon::class, 'coupon')
            ->where('coupon.tenant = :tenant')
            ->setParameter('tenant', $this->activeTenant->get());

        if ($query !== '') {
            $couponsQuery->andWhere('LOWER(coupon.title) LIKE :query')->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        $total = (int) (clone $couponsQuery)->select('COUNT(coupon.id)')->getQuery()->getSingleScalarResult();
        $totalPages = $pageSize === null ? 1 : max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $couponsQuery->orderBy('coupon.id', 'DESC');
        if ($pageSize !== null) {
            $couponsQuery->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize);
        }
        $items = $couponsQuery->getQuery()->getResult();

        return new JsonResponse([
            'coupons' => array_map(fn (Coupon $coupon) => $this->serialize($coupon, $request), $items),
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/api/v1/admin/coupons/{id}', methods: ['GET'])]
    public function one(int $id, Request $request): JsonResponse
    {
        $coupon = $this->find($id);
        if (!$this->isAdmin() || !$coupon instanceof Coupon) {
            return new JsonResponse(['message' => 'Gutschein nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['coupon' => $this->serialize($coupon, $request)]);
    }

    #[Route('/api/v1/admin/coupons', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        [$title, $subtitle, $description, $from, $until] = $this->data($request);
        $image = $this->mediaPath($request);
        if (!$this->isValidData($title, $subtitle, $description, $from, $until, $image)) {
            return new JsonResponse(['message' => 'Ungültige Gutscheinangaben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coupon = new Coupon(
            $this->activeTenant->get(),
            $title,
            $subtitle,
            $description,
            $image,
            'true' === $request->request->get('isVisible', 'true'),
            $from,
            $until,
        );
        $this->entityManager->persist($coupon);
        $this->entityManager->flush();

        return new JsonResponse(['coupon' => $this->serialize($coupon, $request)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/coupons/{id}/update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coupon = $this->find($id);
        if (!$this->isAdmin() || !$coupon instanceof Coupon) {
            return new JsonResponse(['message' => 'Gutschein nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        [$title, $subtitle, $description, $from, $until] = $this->data($request);
        $image = $this->mediaPath($request);
        if (!$this->isValidData($title, $subtitle, $description, $from, $until, $image)) {
            return new JsonResponse(['message' => 'Ungültige Gutscheinangaben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coupon->update(
            $title,
            $subtitle,
            $description,
            $request->request->has('mediaPath') ? $image : $coupon->getImagePath(),
            'true' === $request->request->get('isVisible', 'true'),
            $from,
            $until,
        );
        $this->entityManager->flush();

        return new JsonResponse(['coupon' => $this->serialize($coupon, $request)]);
    }

    #[Route('/api/v1/admin/coupons/{id}/visibility', methods: ['PATCH'])]
    public function visibility(int $id, Request $request): JsonResponse
    {
        $coupon = $this->find($id);
        $visible = $request->toArray()['isVisible'] ?? null;
        if (!$this->isAdmin() || !$coupon instanceof Coupon || !is_bool($visible)) {
            return new JsonResponse(['message' => 'Ungültig.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coupon->setVisible($visible);
        $this->entityManager->flush();

        return new JsonResponse(['coupon' => $this->serialize($coupon, $request)]);
    }

    #[Route('/api/v1/admin/coupons/{id}', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $coupon = $this->find($id);
        if (!$coupon instanceof Coupon) {
            return new JsonResponse(['message' => 'Gutschein nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($coupon);
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array{string, string, string, \DateTimeImmutable|false|null, \DateTimeImmutable|false|null} */
    private function data(Request $request): array
    {
        return [
            trim((string) $request->request->get('title', '')),
            trim((string) $request->request->get('subtitle', '')),
            $this->richText->sanitize((string) $request->request->get('description', '')),
            $this->date($request->request->get('availableFrom')),
            $this->date($request->request->get('availableUntil')),
        ];
    }

    private function mediaPath(Request $request): string|false
    {
        if (!$request->request->has('mediaPath')) {
            return '';
        }

        $path = $request->request->get('mediaPath');
        $asset = is_string($path)
            ? $this->entityManager->getRepository(MediaAsset::class)->findOneBy([
                'path' => $path,
                'tenant' => $this->activeTenant->get(),
                'visibility' => 'public',
            ])
            : null;

        return $asset instanceof MediaAsset ? $asset->getPath() : false;
    }

    private function date(mixed $value): \DateTimeImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value);

        return $date !== false && $date->format('Y-m-d\\TH:i') === $value ? $date : false;
    }

    private function find(int $id): ?Coupon
    {
        $coupon = $this->entityManager->getRepository(Coupon::class)->find($id);

        return $coupon instanceof Coupon && $coupon->getTenant() === $this->activeTenant->get()
            ? $coupon
            : null;
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());

        return $membership !== null
            && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }

    private function adminPageSize(Request $request): ?int
    {
        if ($request->query->get('pageSize') === 'all') {
            return null;
        }

        $pageSize = $request->query->getInt('pageSize', 25);
        return in_array($pageSize, [10, 25, 50], true) ? $pageSize : 25;
    }

    private function isValidData(
        string $title,
        string $subtitle,
        string $description,
        \DateTimeImmutable|false|null $from,
        \DateTimeImmutable|false|null $until,
        string|false $image,
    ): bool {
        return $title !== ''
            && $subtitle !== ''
            && $description !== ''
            && $from !== false
            && $until !== false
            && $image !== false
            && !($from instanceof \DateTimeImmutable && $until instanceof \DateTimeImmutable && $from > $until);
    }

    /** @return array{id:int,title:string,subtitle:string,description:string,imageUrl:?string,isVisible:bool,availableFrom:?string,availableUntil:?string} */
    private function serialize(Coupon $coupon, Request $request): array
    {
        return [
            'id' => $coupon->getId(),
            'title' => $coupon->getTitle(),
            'subtitle' => $coupon->getSubtitle(),
            'description' => $coupon->getDescription(),
            'imageUrl' => $coupon->getImagePath() === '' ? null : $request->getSchemeAndHttpHost().$coupon->getImagePath(),
            'isVisible' => $coupon->isVisible(),
            'availableFrom' => $coupon->getAvailableFrom()?->format(DATE_ATOM),
            'availableUntil' => $coupon->getAvailableUntil()?->format(DATE_ATOM),
        ];
    }
}
