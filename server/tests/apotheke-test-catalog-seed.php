<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$tenant = (new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']))->get();
$command = new App\Command\SeedApothekeTestCatalogCommand(
    $entityManager,
    new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']),
);
$tester = new Symfony\Component\Console\Tester\CommandTester($command);

function catalogCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$entityManager->beginTransaction();
try {
    catalogCheck($tester->execute([]) === Symfony\Component\Console\Command\Command::FAILURE, 'Seed must require explicit confirmation.');
    catalogCheck($tester->execute(['--confirm' => true]) === Symfony\Component\Console\Command\Command::SUCCESS, 'Confirmed seed must succeed.');

    $news = $entityManager->getRepository(App\Entity\NewsPost::class)->findOneBy(['tenant' => $tenant, 'title' => 'TEST · Reiseapotheke planen']);
    $coupon = $entityManager->getRepository(App\Entity\Coupon::class)->findOneBy(['tenant' => $tenant, 'title' => 'TEST · Pflegeprobe für trockene Hände']);
    $reward = $entityManager->getRepository(App\Entity\Reward::class)->findOneBy(['tenant' => $tenant, 'title' => 'TEST · Kräutertee-Mischung']);

    catalogCheck($news instanceof App\Entity\NewsPost && $news->isVisible() && str_contains($news->getBodyHtml(), 'Fiktiver Testinhalt'), 'Visible test news must be clearly labelled.');
    catalogCheck($coupon instanceof App\Entity\Coupon && $coupon->isVisible() && str_contains($coupon->getDescription(), 'kein gültiges Angebot'), 'Visible test coupons must not imply a real offer.');
    catalogCheck($reward instanceof App\Entity\Reward && $reward->isVisible() && $reward->getRequiredPoints() === 250, 'Visible test rewards must have a usable point cost.');
    foreach ([$news->getImagePath(), $coupon->getImagePath(), $reward->getImagePath()] as $imagePath) {
        catalogCheck(is_file(dirname(__DIR__).'/public'.$imagePath), 'Every seeded image must exist in the deployable demo assets.');
    }

    catalogCheck($tester->execute(['--confirm' => true]) === Symfony\Component\Console\Command\Command::SUCCESS, 'Seed must be repeatable.');
    catalogCheck(str_contains($tester->getDisplay(), '0 Inhalte, 0 Gutscheine und 0 Prämien'), 'Repeat run must not create duplicate entries.');

    $foreign = new App\Entity\Tenant('Other Pharmacy', 'other-pharmacy-test');
    $entityManager->persist($foreign);
    $entityManager->flush();
    $foreignTester = new Symfony\Component\Console\Tester\CommandTester(new App\Command\SeedApothekeTestCatalogCommand(
        $entityManager,
        new App\Service\ActiveTenantProvider($entityManager, 'other-pharmacy-test'),
    ));
    catalogCheck($foreignTester->execute(['--confirm' => true]) === Symfony\Component\Console\Command\Command::FAILURE, 'Seed must not write to another tenant.');

    echo "Apotheke test catalog seed checks passed.\n";
} finally {
    $entityManager->getConnection()->rollBack();
    $kernel->shutdown();
}
