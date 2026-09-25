<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsCategory;
use App\Entity\NewsPost;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiNewsCategoryController
{
    public function __construct(
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/v1/news/categories', methods: ['GET'], priority: 30)]
    public function list(): JsonResponse
    {
        if (!$this->membership()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $categories = $this->em->getRepository(NewsCategory::class)->findBy(['tenant' => $this->activeTenant->get()], ['name' => 'ASC']);
        return new JsonResponse(['categories' => array_map(fn (NewsCategory $category) => $this->serialize($category), $categories)]);
    }

    #[Route('/api/v1/admin/news/categories', methods: ['GET'], priority: 30)]
    public function adminList(): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        return $this->list();
    }

    #[Route('/api/v1/admin/news/categories', methods: ['POST'], priority: 30)]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        try { $data = $request->toArray(); } catch (JsonException) { return $this->invalid(); }
        $name = $this->name($data['name'] ?? null);
        if ($name === null) return $this->invalid();
        $category = new NewsCategory($this->activeTenant->get(), $name);
        $this->em->persist($category);
        try { $this->em->flush(); } catch (UniqueConstraintViolationException) { return $this->duplicate(); }
        return new JsonResponse(['category' => $this->serialize($category)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/news/categories/{id}', methods: ['PATCH'], priority: 30)]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $category = $this->find($id);
        if (!$category) return $this->notFound();
        try { $data = $request->toArray(); } catch (JsonException) { return $this->invalid(); }
        $name = $this->name($data['name'] ?? null);
        if ($name === null) return $this->invalid();
        $category->setName($name);
        try { $this->em->flush(); } catch (UniqueConstraintViolationException) { return $this->duplicate(); }
        return new JsonResponse(['category' => $this->serialize($category)]);
    }

    #[Route('/api/v1/admin/news/categories/{id}', methods: ['DELETE'], priority: 30)]
    public function delete(int $id): Response
    {
        if (!$this->isAdmin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $category = $this->find($id);
        if (!$category) return $this->notFound();
        $tenant = $this->activeTenant->get();
        foreach ($this->em->getRepository(NewsPost::class)->findBy(['tenant' => $tenant]) as $post) {
            $post->setCategoryIds(array_values(array_diff($post->getCategoryIds(), [$id])));
        }
        foreach ($this->em->getRepository(TenantMembership::class)->findBy(['tenant' => $tenant]) as $membership) {
            $membership->setNewsCategoryIds(array_values(array_diff($membership->getNewsCategoryIds(), [$id])));
        }
        $this->em->remove($category);
        $this->em->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function membership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $this->memberships->findForUserAndTenant($user, $this->activeTenant->get()) : null;
    }

    private function isAdmin(): bool
    {
        $membership = $this->membership();
        return $membership !== null && (in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true) || in_array('ROLE_TENANT_STAFF', $membership->getRoles(), true));
    }

    private function find(int $id): ?NewsCategory
    {
        $category = $this->em->getRepository(NewsCategory::class)->find($id);
        return $category instanceof NewsCategory && $category->getTenant() === $this->activeTenant->get() ? $category : null;
    }

    private function name(mixed $name): ?string
    {
        $name = is_string($name) ? trim($name) : '';
        return $name !== '' && mb_strlen($name) <= 100 ? $name : null;
    }

    /** @return array{id:int,name:string} */
    private function serialize(NewsCategory $category): array { return ['id' => $category->getId(), 'name' => $category->getName()]; }
    private function invalid(): JsonResponse { return new JsonResponse(['message' => 'Bitte einen Kategorienamen mit maximal 100 Zeichen eingeben.'], Response::HTTP_UNPROCESSABLE_ENTITY); }
    private function duplicate(): JsonResponse { return new JsonResponse(['message' => 'Diese Kategorie gibt es bereits.'], Response::HTTP_CONFLICT); }
    private function notFound(): JsonResponse { return new JsonResponse(['message' => 'Kategorie nicht gefunden.'], Response::HTTP_NOT_FOUND); }
}
