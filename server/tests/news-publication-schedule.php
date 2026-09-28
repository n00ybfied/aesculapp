<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();
$connection->beginTransaction();

try {
    $tenant = (new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']))->get();
    $now = new DateTimeImmutable('2026-09-28 12:00:00');
    $future = $now->modify('+1 day');
    $post = new App\Entity\NewsPost($tenant, 'Zeitplan Test', 'Test', '<p>Test</p>', null, false, $now, $future, null);
    $em->persist($post);
    $em->flush();

    $isReady = static function () use ($em, $post, $now): bool {
        return (int) $em->createQueryBuilder()
            ->select('COUNT(p.id)')->from(App\Entity\NewsPost::class, 'p')
            ->where('p.id = :id')->andWhere('p.isVisible = true')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('(p.showFrom IS NULL OR p.showFrom <= :now)')
            ->andWhere('(p.showUntil IS NULL OR p.showUntil >= :now)')
            ->setParameter('id', $post->getId())->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult() === 1;
    };

    if ($isReady()) throw new RuntimeException('Draft was treated as published.');
    $post->update('Zeitplan Test', 'Test', '<p>Test</p>', null, true, $now, $future, null);
    $em->flush();
    if ($isReady()) throw new RuntimeException('Future display start was ignored.');
    $post->update('Zeitplan Test', 'Test', '<p>Test</p>', null, true, $now, $now, null);
    $em->flush();
    if (!$isReady()) throw new RuntimeException('Published post was not available at display start.');

    echo "News publication schedule checks passed.\n";
} finally {
    $connection->rollBack();
    $em->clear();
    $kernel->shutdown();
}
