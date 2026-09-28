<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();
$connection->beginTransaction();

function referralCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $tenant = (new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']))->get();
    $tenant->setReferralBonusPoints(75);
    $suffix = bin2hex(random_bytes(6));
    $inviter = new App\Entity\User("referrer-{$suffix}@example.invalid", "referrer-{$suffix}@example.invalid", 'Einladender Testkunde');
    $invitee = new App\Entity\User("invitee-{$suffix}@example.invalid", "invitee-{$suffix}@example.invalid", 'Eingeladener Testkunde');
    $inviter->setPassword('not-a-login');
    $invitee->setPassword('not-a-login');
    $membership = new App\Entity\TenantMembership($tenant, $inviter);
    $em->persist($inviter);
    $em->persist($invitee);
    $em->persist($membership);
    $em->flush();

    $service = new App\Service\CustomerReferralService($em, new App\Service\PointAccountProvisioner($em));
    $code = $service->codeFor($membership);
    referralCheck($service->findInviter($tenant, $code)?->getId() === $membership->getId(), 'The valid code must identify the customer in the same tenant.');
    referralCheck($service->findInviter($tenant, str_repeat('0', 32)) === null, 'An unknown code must be rejected.');

    $service->recordInvitation($tenant, $membership, $invitee);
    $em->flush();
    $success = $service->awardVerifiedInvitation($tenant, $invitee);
    $em->flush();
    $duplicate = $service->awardVerifiedInvitation($tenant, $invitee);
    $em->flush();

    referralCheck($success !== null && $success['inviter']->getId() === $inviter->getId() && $success['points'] === 75, 'A verified friend must yield one notification event with the credited points.');
    referralCheck($duplicate === null, 'Repeated verification must not yield another notification event.');

    $transactions = $connection->fetchAllAssociative("SELECT pt.points FROM point_transaction pt JOIN point_account pa ON pa.id = pt.account_id WHERE pa.owner_id = ? AND pa.tenant_id = ? AND pt.type = 'referral_bonus'", [$inviter->getId(), $tenant->getId()]);
    referralCheck(count($transactions) === 1 && (int) $transactions[0]['points'] === 75, 'A verified friend must credit the inviter exactly once.');
    referralCheck($service->successfulCount($membership) === 1, 'The successful invitation count must be one.');

    $tenant->setReferralBonusPoints(0);
    $secondInvitee = new App\Entity\User("invitee-zero-{$suffix}@example.invalid", "invitee-zero-{$suffix}@example.invalid", 'Eingeladener ohne Bonus');
    $secondInvitee->setPassword('not-a-login');
    $em->persist($secondInvitee);
    $service->recordInvitation($tenant, $membership, $secondInvitee);
    $em->flush();
    $zeroBonus = $service->awardVerifiedInvitation($tenant, $secondInvitee);
    $em->flush();
    referralCheck($zeroBonus !== null && $zeroBonus['points'] === 0, 'A confirmed invitation with zero bonus must still yield a notification event.');
    referralCheck(count($connection->fetchAllAssociative("SELECT pt.id FROM point_transaction pt JOIN point_account pa ON pa.id = pt.account_id WHERE pa.owner_id = ? AND pa.tenant_id = ? AND pt.type = 'referral_bonus'", [$inviter->getId(), $tenant->getId()])) === 1, 'A zero bonus must not create a points transaction.');

    echo "Customer referrals: code lookup, verified bonus, zero bonus and duplicate protection passed.\n";
} finally {
    while ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
    $em->clear();
    $kernel->shutdown();
}
