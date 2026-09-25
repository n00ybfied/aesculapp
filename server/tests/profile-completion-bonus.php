<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');

use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\PointAccountProvisioner;
use App\Service\ProfileCompletionBonusService;

$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$bonus = new ProfileCompletionBonusService($em, new PointAccountProvisioner($em));
$em->beginTransaction();

try {
    $slug = 'profile-bonus-test-'.bin2hex(random_bytes(5));
    $tenant = new Tenant('Profile bonus test', $slug);
    $tenant->setInitialPoints(100);
    $tenant->setProfileCompletionBonusPoints(0);
    $email = $slug.'@example.invalid';
    $user = new User($email, $email, 'Neuer Kunde');
    $user->setPassword('test-only');
    $membership = new TenantMembership($tenant, $user);
    $em->persist($tenant);
    $em->persist($user);
    $em->persist($membership);
    $em->flush();

    if ($bonus->awardIfEligible($membership) !== 0) throw new RuntimeException('Incomplete setup must not earn points.');
    if (count($bonus->missingFields($membership)) !== 8) throw new RuntimeException('The achievement must show eight missing profile details.');
    $user->setNames('Erika', null);
    $membership->completeCustomerSetup();
    if (!in_array('Nachname', $bonus->missingFields($membership), true) || in_array('Vorname', $bonus->missingFields($membership), true)) {
        throw new RuntimeException('A partially completed profile must report only genuinely missing details.');
    }
    $user->setNames('Erika', 'Beispiel');
    $user->setSalutation('frau');
    $user->setPhone('+43 123 456');
    $user->setStreetAddress('Hauptstraße 1');
    $user->setPostalCode('8793');
    $user->setCity('Trofaiach');
    if ($bonus->awardIfEligible($membership) !== 0) throw new RuntimeException('Missing birthday must not earn points.');
    $user->setBirthDate(new DateTimeImmutable('1990-06-15'));
    if (!$bonus->isComplete($membership)) throw new RuntimeException('All personal fields must qualify.');
    if ($bonus->awardIfEligible($membership) !== 0) throw new RuntimeException('A disabled bonus must not book points.');
    $tenant->setProfileCompletionBonusPoints(55);
    if ($bonus->awardIfEligible($membership) !== 55) throw new RuntimeException('The configured bonus must be awarded.');
    $em->flush();
    if ($bonus->awardIfEligible($membership) !== 0) throw new RuntimeException('The bonus must only be awarded once.');

    $account = $em->getRepository(PointAccount::class)->findOneBy(['tenant' => $tenant, 'owner' => $user]);
    if (!$account instanceof PointAccount) throw new RuntimeException('Point account is missing.');
    $transactions = $em->getRepository(PointTransaction::class)->findBy(['account' => $account]);
    $pointsByType = [];
    foreach ($transactions as $transaction) $pointsByType[$transaction->getType()] = $transaction->getPoints();
    ksort($pointsByType);
    if (count($transactions) !== 2 || $pointsByType !== ['initial_credit' => 100, 'profile_completion_bonus' => 55]) {
        throw new RuntimeException('Start credit and profile bonus must remain separate ledger entries.');
    }

    $tenant->setProfileCompletionBonusPoints(70);
    if ($bonus->awardIfEligible($membership) !== 0) throw new RuntimeException('Changing the configured amount must not grant a second bonus.');
    if ($bonus->awardedPoints($membership) !== 55) throw new RuntimeException('The achievement must retain the points actually awarded.');
    echo "Profile completion bonus checks passed.\n";
} finally {
    while ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
    $kernel->shutdown();
}
