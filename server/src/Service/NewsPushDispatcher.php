<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsPost;
use App\Entity\NewsPushDelivery;
use App\Entity\TenantMembership;
use Doctrine\ORM\EntityManagerInterface;

final class NewsPushDispatcher
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ChatPushService $push) {}

    /** @return array{queued:int,sent:int,failed:int} */
    public function run(): array
    {
        $now = new \DateTimeImmutable();
        $posts = $this->em->createQueryBuilder()
            ->select('post')->from(NewsPost::class, 'post')
            ->where('post.isVisible = true')->andWhere('post.newsPushQueuedAt IS NULL')
            ->andWhere('post.publishedAt <= :now')
            ->andWhere('(post.showFrom IS NULL OR post.showFrom <= :now)')
            ->andWhere('(post.showUntil IS NULL OR post.showUntil >= :now)')
            ->setParameter('now', $now)->orderBy('post.id', 'DESC')->setMaxResults(20)->getQuery()->getResult();

        $queued = 0;
        foreach ($posts as $post) {
            $this->em->wrapInTransaction(function () use ($post, &$queued): void {
                $categories = $post->getCategoryIds();
                if ($categories !== []) {
                    foreach ($this->em->getRepository(TenantMembership::class)->findBy(['tenant' => $post->getTenant()]) as $membership) {
                        if (!self::matchesAudience($post, $membership)) continue;
                        $this->em->persist(new NewsPushDelivery($post, $membership->getUser()));
                        ++$queued;
                    }
                }
                $post->markNewsPushQueued();
            });
        }

        $sent = 0;
        $failed = 0;
        $deliveries = $this->em->getRepository(NewsPushDelivery::class)->findBy(['sentAt' => null], ['attempts' => 'ASC', 'id' => 'ASC'], 20);
        foreach ($deliveries as $delivery) {
            $post = $delivery->getPost();
            if (!$post->isVisible() || ($post->getShowUntil() !== null && $post->getShowUntil() < $now)) {
                $delivery->markSent();
                ++$sent;
                continue;
            }
            if ($this->push->sendNewsNotification($delivery->getUser(), $post->getTenant(), (int) $post->getId())) {
                $delivery->markSent();
                ++$sent;
            } else {
                $delivery->markFailed();
                ++$failed;
            }
            $this->em->flush();
        }
        $this->em->flush();
        return compact('queued', 'sent', 'failed');
    }

    public static function matchesAudience(NewsPost $post, TenantMembership $membership): bool
    {
        if ($post->getTenant() !== $membership->getTenant() || !$membership->getUser()->isActive()) return false;
        if (!in_array('ROLE_CUSTOMER', $membership->getRoles(), true) || !$membership->isNewsPushEnabled()) return false;
        if ($post->getCategoryIds() === [] || array_intersect($post->getCategoryIds(), $membership->getNewsCategoryIds()) === []) return false;
        $salutations = $post->getNotificationSalutations();
        return $salutations === [] || in_array($membership->getUser()->getSalutation(), $salutations, true);
    }
}
