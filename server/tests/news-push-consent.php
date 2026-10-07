<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');

use App\Entity\NewsPushConsentEvent;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\NewsPushConsentRecorder;

$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$recorder = new NewsPushConsentRecorder($em);
$em->beginTransaction();

try {
    $slug = 'news-consent-test-'.bin2hex(random_bytes(5));
    $tenant = new Tenant('News consent test', $slug);
    $email = $slug.'@example.invalid';
    $user = new User($email, $email, 'Testkunde');
    $user->setPassword('test-only');
    $membership = new TenantMembership($tenant, $user);
    $em->persist($tenant);
    $em->persist($user);
    $em->persist($membership);
    $em->flush();

    $recorder->update($membership, false, 'setup');
    $recorder->update($membership, true, 'setup');
    $recorder->update($membership, true, 'profile');
    $em->flush();

    $events = $em->getRepository(NewsPushConsentEvent::class)->findBy(['membership' => $membership]);
    if (count($events) !== 1 || !$membership->isNewsPushEnabled()) throw new RuntimeException('Only a real opt-in must be recorded.');
    if ($events[0]->getAction() !== 'granted' || $events[0]->getSource() !== 'setup'
        || $events[0]->getTextVersion() !== NewsPushConsentRecorder::TEXT_VERSION
        || $events[0]->getConsentText() !== NewsPushConsentRecorder::CONSENT_TEXT) {
        throw new RuntimeException('The opt-in must retain its source and exact text.');
    }

    $recorder->update($membership, false, 'profile');
    $em->flush();
    $events = $em->getRepository(NewsPushConsentEvent::class)->findBy(['membership' => $membership], ['id' => 'ASC']);
    if (count($events) !== 2 || $events[1]->getAction() !== 'withdrawn' || $events[1]->getSource() !== 'profile'
        || $membership->isNewsPushEnabled()) {
        throw new RuntimeException('A withdrawal must be recorded and stop news push.');
    }

    echo "News push consent checks passed.\n";
} finally {
    while ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
    $kernel->shutdown();
}
