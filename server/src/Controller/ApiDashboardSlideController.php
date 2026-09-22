<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DashboardSlide;
use App\Entity\MediaAsset;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDashboardSlideController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
    ) {}

    #[Route('/api/v1/dashboard/slides', methods: ['GET'])]
    public function publicList(Request $request): JsonResponse
    {
        return new JsonResponse(['slides' => $this->serializeAll($request), 'settings' => $this->settings()]);
    }

    #[Route('/api/v1/admin/dashboard/slides', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        return new JsonResponse(['slides' => $this->serializeAll($request), 'settings' => $this->settings()]);
    }

    #[Route('/api/v1/admin/dashboard/slides', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $imagePath = $this->imagePath($request);
        $linkUrl = $this->linkUrl($request);
        if ($imagePath === null || $linkUrl === false) return new JsonResponse(['message' => 'Bitte wählen Sie ein Bild und prüfen Sie den optionalen Link.'], 422);

        $position = $this->entityManager->getRepository(DashboardSlide::class)->count(['tenant' => $this->activeTenant->get()]);
        $slide = new DashboardSlide($this->activeTenant->get(), $imagePath, $linkUrl, $position);
        $this->entityManager->persist($slide);
        $this->entityManager->flush();
        return new JsonResponse(['slide' => $this->serialize($slide, $request)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/dashboard/slides/{id}', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $slide = $this->slide($id);
        if ($slide === null) return new JsonResponse(['message' => 'Sliderbild nicht gefunden.'], 404);
        $imagePath = $this->imagePath($request);
        $linkUrl = $this->linkUrl($request);
        if ($imagePath === null || $linkUrl === false) return new JsonResponse(['message' => 'Bitte wählen Sie ein Bild und prüfen Sie den optionalen Link.'], 422);

        $slide->setImagePath($imagePath);
        $slide->setLinkUrl($linkUrl);
        $this->entityManager->flush();
        return new JsonResponse(['slide' => $this->serialize($slide, $request)]);
    }

    #[Route('/api/v1/admin/dashboard/slides/{id}', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $slide = $this->slide($id);
        if ($slide === null) return new JsonResponse(['message' => 'Sliderbild nicht gefunden.'], 404);
        $this->entityManager->remove($slide);
        $this->entityManager->flush();
        $this->normalizePositions();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/v1/admin/dashboard/slides/order', methods: ['PUT'])]
    public function reorder(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        try { $input = $request->toArray(); } catch (\JsonException) { return new JsonResponse(['message' => 'Ungültige Reihenfolge.'], 422); }
        $ids = $input['ids'] ?? null;
        if (!is_array($ids)) return new JsonResponse(['message' => 'Ungültige Reihenfolge.'], 422);
        $slides = $this->slides();
        $expected = array_map(static fn(DashboardSlide $slide): int => $slide->getId() ?? 0, $slides);
        $received = array_map(static fn(mixed $id): int => is_int($id) ? $id : 0, $ids);
        sort($expected); sort($received);
        if ($expected !== $received) return new JsonResponse(['message' => 'Ungültige Reihenfolge.'], 422);
        $byId = [];
        foreach ($slides as $slide) { $byId[$slide->getId() ?? 0] = $slide; }
        foreach ($ids as $position => $id) { $byId[$id]->setPosition($position); }
        $this->entityManager->flush();
        return new JsonResponse(['slides' => $this->serializeAll($request)]);
    }

    #[Route('/api/v1/admin/dashboard/slides/settings', methods: ['PUT'])]
    public function updateSettings(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        try { $input = $request->toArray(); } catch (\JsonException) { return new JsonResponse(['message' => 'Ungültige Slider-Einstellungen.'], 422); }
        $transition = $input['transition'] ?? null;
        $animationDurationMs = $input['animationDurationMs'] ?? null;
        $delayMs = $input['delayMs'] ?? null;
        $autoplay = $input['autoplay'] ?? null;
        if (!is_string($transition) || !in_array($transition, ['fade', 'slide'], true)
            || !is_int($animationDurationMs) || $animationDurationMs < 100 || $animationDurationMs > 3_000
            || !is_int($delayMs) || $delayMs < 1_000 || $delayMs > 60_000
            || !is_bool($autoplay)) {
            return new JsonResponse(['message' => 'Bitte wählen Sie einen Übergang sowie gültige Zeiten.'], 422);
        }
        $tenant = $this->activeTenant->get();
        $tenant->setDashboardSliderTransition($transition);
        $tenant->setDashboardSliderAnimationDurationMs($animationDurationMs);
        $tenant->setDashboardSliderDelayMs($delayMs);
        $tenant->setDashboardSliderAutoplay($autoplay);
        $this->entityManager->flush();
        return new JsonResponse(['settings' => $this->settings()]);
    }

    /** @return list<array{id:int,imageUrl:string,linkUrl:?string,position:int}> */
    private function serializeAll(Request $request): array
    {
        return array_map(fn(DashboardSlide $slide): array => $this->serialize($slide, $request), $this->slides());
    }

    /** @return list<DashboardSlide> */
    private function slides(): array
    {
        return $this->entityManager->getRepository(DashboardSlide::class)->findBy(['tenant' => $this->activeTenant->get()], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return array{id:int,imageUrl:string,linkUrl:?string,position:int} */
    private function serialize(DashboardSlide $slide, Request $request): array
    {
        return ['id' => $slide->getId() ?? 0, 'imageUrl' => $request->getSchemeAndHttpHost().$slide->getImagePath(), 'linkUrl' => $slide->getLinkUrl(), 'position' => $slide->getPosition()];
    }

    private function slide(int $id): ?DashboardSlide
    {
        $slide = $this->entityManager->getRepository(DashboardSlide::class)->find($id);
        return $slide instanceof DashboardSlide && $slide->getTenant() === $this->activeTenant->get() ? $slide : null;
    }

    private function imagePath(Request $request): ?string
    {
        $path = $request->request->get('imagePath');
        if (!is_string($path) || !str_starts_with($path, '/uploads/media/')) return null;
        $asset = $this->entityManager->getRepository(MediaAsset::class)->findOneBy(['tenant' => $this->activeTenant->get(), 'path' => $path, 'visibility' => 'public']);
        return $asset instanceof MediaAsset ? $asset->getPath() : null;
    }

    private function linkUrl(Request $request): string|false|null
    {
        $link = trim((string) $request->request->get('linkUrl', ''));
        if ($link === '') return null;
        if (mb_strlen($link) > 2048) return false;
        if (str_starts_with($link, '/')) return !str_starts_with($link, '//') ? $link : false;
        $parts = parse_url($link);
        return filter_var($link, FILTER_VALIDATE_URL) !== false && is_array($parts) && ($parts['scheme'] ?? null) === 'https' && isset($parts['host']) ? $link : false;
    }

    private function normalizePositions(): void
    {
        foreach ($this->slides() as $position => $slide) { $slide->setPosition($position); }
        $this->entityManager->flush();
    }

    /** @return array{transition:string,animationDurationMs:int,delayMs:int,autoplay:bool} */
    private function settings(): array
    {
        $tenant = $this->activeTenant->get();
        return ['transition' => $tenant->getDashboardSliderTransition(), 'animationDurationMs' => $tenant->getDashboardSliderAnimationDurationMs(), 'delayMs' => $tenant->getDashboardSliderDelayMs(), 'autoplay' => $tenant->isDashboardSliderAutoplay()];
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }
}
