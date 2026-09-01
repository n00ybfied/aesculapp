<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsPost;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ApiNewsController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/news', name: 'api_v1_news', methods: ['GET'])]
    public function customerList(Request $request): JsonResponse
    {
        $now = new \DateTimeImmutable();
        $posts = $this->entityManager->createQueryBuilder()
            ->select('post')->from(NewsPost::class, 'post')
            ->where('post.tenant = :tenant')->andWhere('post.isVisible = true')
            ->andWhere('(post.showFrom IS NULL OR post.showFrom <= :now)')
            ->andWhere('(post.showUntil IS NULL OR post.showUntil >= :now)')
            ->setParameter('tenant', $this->activeTenant->get())->setParameter('now', $now)
            ->orderBy('post.publishedAt', 'DESC')->setMaxResults(3)->getQuery()->getResult();

        return new JsonResponse(['posts' => array_map(fn (NewsPost $post) => $this->serialize($post, $request), $posts)]);
    }

    #[Route('/api/v1/news/{id}', name: 'api_v1_news_one', methods: ['GET'], priority: 10)]
    public function customerOne(int $id, Request $request): JsonResponse
    {
        $post = $this->findVisiblePost($id);
        return $post instanceof NewsPost
            ? new JsonResponse(['post' => $this->serialize($post, $request)])
            : new JsonResponse(['message' => 'Beitrag nicht gefunden.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/v1/admin/news', name: 'api_v1_admin_news', methods: ['GET'])]
    public function adminList(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(50, max(1, $request->query->getInt('pageSize', 10)));
        $repository = $this->entityManager->getRepository(NewsPost::class);
        $criteria = ['tenant' => $this->activeTenant->get()];
        $total = $repository->count($criteria);
        $posts = $repository->findBy($criteria, ['publishedAt' => 'DESC', 'id' => 'DESC'], $pageSize, ($page - 1) * $pageSize);

        return new JsonResponse(['posts' => array_map(fn (NewsPost $post) => $this->serialize($post, $request), $posts), 'page' => $page, 'total' => $total, 'totalPages' => (int) ceil($total / $pageSize)]);
    }

    #[Route('/api/v1/admin/news/{id}', name: 'api_v1_admin_news_one', methods: ['GET'])]
    public function adminOne(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $post = $this->findTenantPost($id);
        return $post instanceof NewsPost ? new JsonResponse(['post' => $this->serialize($post, $request)]) : new JsonResponse(['message' => 'Post not found.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/v1/admin/news', name: 'api_v1_admin_news_create', methods: ['POST'])]
    public function create(Request $request, SluggerInterface $slugger): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $data = $this->readPostData($request, $slugger);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Bitte prüfen Sie Titel, Untertitel, Inhalt und Zeitangaben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $post = new NewsPost($this->activeTenant->get(), ...$data);
        $this->entityManager->persist($post);
        $this->entityManager->flush();
        return new JsonResponse(['post' => $this->serialize($post, $request)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/news/images', name: 'api_v1_admin_news_image', methods: ['POST'], priority: 10)]
    public function uploadContentImage(Request $request, SluggerInterface $slugger): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile) {
            return new JsonResponse(['message' => 'Bitte wählen Sie ein Bild aus.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $imagePath = $this->storeImage($image, $slugger, 'content');
        if ($imagePath === null) {
            return new JsonResponse(['message' => 'Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new JsonResponse(['imageUrl' => $request->getSchemeAndHttpHost().$imagePath], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/news/{id}', name: 'api_v1_admin_news_update', methods: ['POST'])]
    public function update(int $id, Request $request, SluggerInterface $slugger): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $post = $this->findTenantPost($id);
        if (!$post instanceof NewsPost) {
            return new JsonResponse(['message' => 'Post not found.'], Response::HTTP_NOT_FOUND);
        }
        $data = $this->readPostData($request, $slugger, $post->getImagePath());
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Bitte prüfen Sie Titel, Untertitel, Inhalt und Zeitangaben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $post->update(...$data);
        $this->entityManager->flush();
        return new JsonResponse(['post' => $this->serialize($post, $request)]);
    }

    #[Route('/api/v1/admin/news/{id}', name: 'api_v1_admin_news_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $post = $this->findTenantPost($id);
        if (!$post instanceof NewsPost) {
            return new JsonResponse(['message' => 'Post not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->deleteImage($post->getImagePath());
        $this->entityManager->remove($post);
        $this->entityManager->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) { return false; }
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }

    private function findTenantPost(int $id): ?NewsPost
    {
        $post = $this->entityManager->getRepository(NewsPost::class)->find($id);
        return $post instanceof NewsPost && $post->getTenant() === $this->activeTenant->get() ? $post : null;
    }

    private function findVisiblePost(int $id): ?NewsPost
    {
        $now = new \DateTimeImmutable();
        $post = $this->entityManager->createQueryBuilder()
            ->select('post')->from(NewsPost::class, 'post')
            ->where('post.id = :id')->andWhere('post.tenant = :tenant')->andWhere('post.isVisible = true')
            ->andWhere('(post.showFrom IS NULL OR post.showFrom <= :now)')->andWhere('(post.showUntil IS NULL OR post.showUntil >= :now)')
            ->setParameter('id', $id)->setParameter('tenant', $this->activeTenant->get())->setParameter('now', $now)
            ->getQuery()->getOneOrNullResult();
        return $post instanceof NewsPost ? $post : null;
    }

    /** @return array{string, string, string, ?string, bool, \DateTimeImmutable, ?\DateTimeImmutable, ?\DateTimeImmutable}|null */
    private function readPostData(Request $request, SluggerInterface $slugger, ?string $existingImagePath = null): ?array
    {
        $title = trim((string) $request->request->get('title', ''));
        $subtitle = trim((string) $request->request->get('subtitle', ''));
        $bodyHtml = $this->sanitizeHtml((string) $request->request->get('bodyHtml', ''));
        $publishedAt = $this->parseDate((string) $request->request->get('publishedAt', ''));
        $showFrom = $this->parseOptionalDate($request->request->get('showFrom'));
        $showUntil = $this->parseOptionalDate($request->request->get('showUntil'));
        if ($title === '' || $subtitle === '' || $bodyHtml === '' || !$publishedAt || ($showFrom && $showUntil && $showFrom > $showUntil)) { return null; }

        $imagePath = $existingImagePath;
        if ('true' === $request->request->get('removeImage')) { $this->deleteImage($imagePath); $imagePath = null; }
        $image = $request->files->get('image');
        if ($image instanceof UploadedFile) {
            $newImage = $this->storeImage($image, $slugger);
            if ($newImage === null) { return null; }
            $this->deleteImage($imagePath);
            $imagePath = $newImage;
        }

        return [$title, $subtitle, $bodyHtml, $imagePath, 'true' === $request->request->get('isVisible', 'true'), $publishedAt, $showFrom, $showUntil];
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        try { return new \DateTimeImmutable($value); } catch (\Exception) { return null; }
    }
    private function parseOptionalDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') { return null; }
        return $this->parseDate($value);
    }

    private function storeImage(UploadedFile $image, SluggerInterface $slugger, string $directoryName = ''): ?string
    {
        if ($image->getSize() > 5 * 1024 * 1024) { return null; }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$image->getMimeType()] ?? null;
        if ($extension === null) { return null; }
        $relativeDirectory = '/uploads/news'.($directoryName === '' ? '' : '/'.$directoryName);
        $directory = dirname(__DIR__, 2).'/public'.$relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { return null; }
        $filename = $slugger->slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)).'-'.bin2hex(random_bytes(8)).'.'.$extension;
        $image->move($directory, $filename);
        return $relativeDirectory.'/'.$filename;
    }

    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath === null) { return; }
        $file = dirname(__DIR__, 2).'/public'.$imagePath;
        if (is_file($file)) { unlink($file); }
    }

    private function sanitizeHtml(string $html): string
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $allowed = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a', 'img', 'h2', 'h3', 'blockquote'];
        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) { return ''; }
        $this->sanitizeNode($root, $allowed);
        $result = '';
        foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
        return trim($result);
    }

    /** @param list<string> $allowed */
    private function sanitizeNode(\DOMNode $node, array $allowed): void
    {
        for ($index = $node->childNodes->length - 1; $index >= 0; --$index) {
            $child = $node->childNodes->item($index);
            if (!$child instanceof \DOMElement) { continue; }
            if (!in_array($child->tagName, $allowed, true)) {
                while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                $node->removeChild($child); continue;
            }
            $href = $child->tagName === 'a' ? trim($child->getAttribute('href')) : '';
            $src = $child->tagName === 'img' ? trim($child->getAttribute('src')) : '';
            $alt = $child->tagName === 'img' ? trim($child->getAttribute('alt')) : '';
            $attributes = [];
            foreach ($child->attributes as $attribute) { $attributes[] = $attribute->name; }
            foreach ($attributes as $attribute) { $child->removeAttribute($attribute); }
            if ($child->tagName === 'a') {
                if ($href !== '' && preg_match('#^(https?://|mailto:)#i', $href)) { $child->setAttribute('href', $href); }
            }
            if ($child->tagName === 'img' && $src !== '' && preg_match('#^https?://#i', $src)) {
                $child->setAttribute('src', $src);
                if ($alt !== '') { $child->setAttribute('alt', $alt); }
            }
            $this->sanitizeNode($child, $allowed);
        }
    }

    /** @return array{id:int,title:string,subtitle:string,bodyHtml:string,imageUrl:?string,isVisible:bool,publishedAt:string,showFrom:?string,showUntil:?string} */
    private function serialize(NewsPost $post, Request $request): array
    {
        return ['id' => $post->getId(), 'title' => $post->getTitle(), 'subtitle' => $post->getSubtitle(), 'bodyHtml' => $post->getBodyHtml(), 'imageUrl' => $post->getImagePath() ? $request->getSchemeAndHttpHost().$post->getImagePath() : null, 'isVisible' => $post->isVisible(), 'publishedAt' => $post->getPublishedAt()->format(DATE_ATOM), 'showFrom' => $post->getShowFrom()?->format(DATE_ATOM), 'showUntil' => $post->getShowUntil()?->format(DATE_ATOM)];
    }
}
