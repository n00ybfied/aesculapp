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
    $post = new App\Entity\NewsPost($tenant, 'Alterszielgruppe Test', 'Test', '<p>Test</p>', null, false, new DateTimeImmutable(), null, null);
    $post->setNotificationAgeRange(18, 65, true);
    $em->persist($post);
    $em->flush();
    $id = $post->getId();
    $em->clear();

    $saved = $em->getRepository(App\Entity\NewsPost::class)->find($id);
    if (!$saved instanceof App\Entity\NewsPost || $saved->getNotificationMinAge() !== 18
        || $saved->getNotificationMaxAge() !== 65 || !$saved->includesMissingBirthDateForNotification()) {
        throw new RuntimeException('News push age targeting was not persisted.');
    }

    echo "News push age targeting persistence passed.\n";
} finally {
    $connection->rollBack();
    $em->clear();
    $kernel->shutdown();
}
