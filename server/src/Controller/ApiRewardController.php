<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reward;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ApiRewardController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageProcessor $imageProcessor,
    ) {
    }

    #[Route('/api/v1/rewards', name: 'api_v1_rewards', methods: ['GET'])]
    public function customerList(Request $request): JsonResponse
    {
        $rewards = $this->entityManager->getRepository(Reward::class)->findBy([
            'tenant' => $this->activeTenant->get(),
            'isVisible' => true,
        ], ['id' => 'DESC']);

        return new JsonResponse(['rewards' => array_map(fn (Reward $reward) => $this->serialize($reward, $request), $rewards)]);
    }

    #[Route('/api/v1/admin/rewards', name: 'api_v1_admin_rewards', methods: ['GET'])]
    public function adminList(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $rewards = $this->entityManager->getRepository(Reward::class)->findBy(['tenant' => $this->activeTenant->get()], ['id' => 'DESC']);
        return new JsonResponse(['rewards' => array_map(fn (Reward $reward) => $this->serialize($reward, $request), $rewards)]);
    }

    #[Route('/api/v1/admin/rewards', name: 'api_v1_admin_reward_create', methods: ['POST'])]
    public function create(Request $request, SluggerInterface $slugger): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $title = trim((string) $request->request->get('title', ''));
        $subtitle = trim((string) $request->request->get('subtitle', ''));
        $description = trim((string) $request->request->get('description', ''));
        $requiredPoints = filter_var($request->request->get('requiredPoints'), FILTER_VALIDATE_INT);
        $image = $request->files->get('image');

        if ($title === '' || $subtitle === '' || $description === '' || false === $requiredPoints || $requiredPoints < 0) {
            return new JsonResponse(['message' => 'Invalid reward data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $imagePath = '';
        if ($image instanceof UploadedFile) {
            $imagePath = $this->storeImage($image, $slugger);
        }
        if ($imagePath === null) {
            return new JsonResponse(['message' => 'Please upload a PNG, JPEG or WebP image up to 5 MB.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reward = new Reward(
            $this->activeTenant->get(),
            $title,
            $subtitle,
            $description,
            $imagePath,
            (int) $requiredPoints,
            'true' === $request->request->get('isVisible', 'true'),
        );
        $this->entityManager->persist($reward);
        $this->entityManager->flush();

        return new JsonResponse(['reward' => $this->serialize($reward, $request)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/rewards/{id}/visibility', name: 'api_v1_admin_reward_visibility', methods: ['PATCH'])]
    public function changeVisibility(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $reward = $this->findTenantReward($id);
        $isVisible = $request->toArray()['isVisible'] ?? null;
        if (!$reward instanceof Reward || !is_bool($isVisible)) {
            return new JsonResponse(['message' => 'Reward not found or invalid visibility.'], Response::HTTP_NOT_FOUND);
        }

        $reward->setVisible($isVisible);
        $this->entityManager->flush();
        return new JsonResponse(['reward' => $this->serialize($reward, $request)]);
    }

    #[Route('/api/v1/admin/rewards/{id}', name: 'api_v1_admin_reward_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $reward = $this->findTenantReward($id);
        if (!$reward instanceof Reward) {
            return new JsonResponse(['message' => 'Reward not found.'], Response::HTTP_NOT_FOUND);
        }

        $file = dirname(__DIR__, 2).'/public'.$reward->getImagePath();
        if (is_file($file)) {
            unlink($file);
        }

        $this->entityManager->remove($reward);
        $this->entityManager->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }

    private function findTenantReward(int $id): ?Reward
    {
        $reward = $this->entityManager->getRepository(Reward::class)->find($id);
        return $reward instanceof Reward && $reward->getTenant() === $this->activeTenant->get() ? $reward : null;
    }

    private function storeImage(UploadedFile $image, SluggerInterface $slugger): ?string
    {
        if ($image->getSize() > 5 * 1024 * 1024) {
            return null;
        }

        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$image->getMimeType()] ?? null;
        if (null === $extension) {
            return null;
        }

        $directory = dirname(__DIR__, 2).'/public/uploads/rewards';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $filename = $slugger->slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)).'-'.bin2hex(random_bytes(8)).'.jpg';
        if (!$this->imageProcessor->saveAdminImage($image, $directory.'/'.$filename)) { return null; }
        return '/uploads/rewards/'.$filename;
    }

    /** @return array{id: int, title: string, subtitle: string, description: string, imageUrl: ?string, requiredPoints: int, isVisible: bool} */
    private function serialize(Reward $reward, Request $request): array
    {
        return [
            'id' => $reward->getId(),
            'title' => $reward->getTitle(),
            'subtitle' => $reward->getSubtitle(),
            'description' => $reward->getDescription(),
            'imageUrl' => $reward->getImagePath() === '' ? null : $request->getSchemeAndHttpHost().$reward->getImagePath(),
            'requiredPoints' => $reward->getRequiredPoints(),
            'isVisible' => $reward->isVisible(),
        ];
    }
}
