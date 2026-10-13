<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$tenant = new App\Service\ActiveTenantProvider($em, (string) $_ENV['APP_TENANT_SLUG']);
$api = new App\Controller\ApiPrizeGameController(
    $em, $security, $tenant, $em->getRepository(App\Entity\TenantMembership::class),
    new App\Service\PointAccountProvisioner($em), new App\Service\PrizeGameDrawService(),
);

function gameCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gameRequest(array $data): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
        json_encode($data, JSON_THROW_ON_ERROR),
    );
}

function gameBody(Symfony\Component\HttpFoundation\JsonResponse $response): array
{
    return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
}

function gamePayload(string $kind, int $stockLimit): array
{
    return [
        'title' => 'Integrationstest Glücksrad', 'isVisible' => true,
        'availableFrom' => (new DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
        'availableUntil' => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM),
        'participationMode' => 'total', 'spinLimit' => 2,
        'noWinEnabled' => false, 'soldOutText' => 'Alle Testgewinne vergeben.',
        'prizes' => [[
            'title' => 'Testgewinn', 'wheelLabel' => 'Gewinn', 'kind' => $kind,
            'points' => $kind === 'points' ? 10 : 0,
            'probabilityBp' => 10000, 'stockLimit' => $stockLimit,
        ]],
    ];
}

$em->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $customer = new App\Entity\User('game-'.$suffix.'@example.invalid', 'game-'.$suffix.'@example.invalid', 'Game Customer');
    $customer->setPassword('not-a-login');
    $staff = new App\Entity\User('game-staff-'.$suffix.'@example.invalid', 'game-staff-'.$suffix.'@example.invalid', 'Game Staff');
    $staff->setPassword('not-a-login');
    $em->persist($customer);
    $em->persist($staff);
    $em->persist(new App\Entity\TenantMembership($tenant->get(), $customer, ['ROLE_CUSTOMER']));
    $em->persist(new App\Entity\TenantMembership($tenant->get(), $staff, ['ROLE_TENANT_ADMIN']));
    $em->flush();
    $login = static function (App\Entity\User $user, array $roles) use ($storage): void {
        $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', $roles));
    };

    $login($staff, ['ROLE_TENANT_ADMIN']);
    $invalid = gamePayload('points', 1);
    $invalid['title'] = ['not a string'];
    gameCheck($api->create(gameRequest($invalid))->getStatusCode() === 422, 'Invalid title must return 422.');
    $pointPayload = gamePayload('points', 2);
    $pointGame = gameBody($api->create(gameRequest($pointPayload)))['game'];
    $couponGame = gameBody($api->create(gameRequest(gamePayload('coupon', 1))))['game'];
    gameCheck($pointGame['status'] === 'active', 'Published game must be active.');

    $login($customer, ['ROLE_CUSTOMER']);
    $requestId = '11111111-1111-4111-8111-'.substr($suffix, 0, 12);
    $first = $api->spin($pointGame['id'], gameRequest(['requestId' => $requestId]));
    gameCheck($first->getStatusCode() === 201 && gameBody($first)['spin']['points'] === 10, 'Point win must succeed.');
    $firstSpin = gameBody($first)['spin'];
    $retry = $api->spin($pointGame['id'], gameRequest(['requestId' => $requestId]));
    gameCheck($retry->getStatusCode() === 200 && gameBody($retry)['spin']['id'] === $firstSpin['id'], 'Retry must return the same spin.');
    $secondId = '22222222-2222-4222-8222-'.substr($suffix, 0, 12);
    gameCheck($api->spin($pointGame['id'], gameRequest(['requestId' => $secondId]))->getStatusCode() === 201, 'Second permitted spin must succeed.');
    gameCheck($api->spin($pointGame['id'], gameRequest(['requestId' => '33333333-3333-4333-8333-'.substr($suffix, 0, 12)]))->getStatusCode() === 409, 'Exhausted game must reject another spin.');
    $transactions = $em->getRepository(App\Entity\PointTransaction::class)->findBy(['type' => 'prize_game_win']);
    $own = array_values(array_filter($transactions, static fn (App\Entity\PointTransaction $item): bool => $item->getAccount()->getOwner() === $customer));
    gameCheck(count($own) === 2, 'Retry must not credit points twice.');

    $coupon = $api->spin($couponGame['id'], gameRequest(['requestId' => '44444444-4444-4444-8444-'.substr($suffix, 0, 12)]));
    $couponSpin = gameBody($coupon)['spin'];
    gameCheck($coupon->getStatusCode() === 201 && preg_match('/^[A-F0-9]{16}$/', $couponSpin['couponCode']) === 1, 'Coupon win needs a one-time code.');
    gameCheck(gameBody($coupon)['game']['status'] === 'sold_out', 'Last limited prize must end the game.');
    gameCheck(count(gameBody($api->customerWins())['wins']) === 1, 'Customer must see the coupon win.');

    $login($staff, ['ROLE_TENANT_ADMIN']);
    $changedRules = $pointPayload;
    $changedRules['spinLimit'] = 3;
    gameCheck($api->update($pointGame['id'], gameRequest($changedRules))->getStatusCode() === 409, 'Rules must remain locked after first spin.');
    $changedTitle = $pointPayload;
    $changedTitle['title'] = 'Neuer sichtbarer Titel';
    gameCheck($api->update($pointGame['id'], gameRequest($changedTitle))->getStatusCode() === 200, 'Game title may change after first spin.');
    $adminWins = gameBody($api->adminWins($couponGame['id']))['wins'];
    gameCheck($adminWins[0]['couponCode'] === null, 'Admin list must not disclose coupon code.');
    gameCheck($api->redeem($couponSpin['id'], gameRequest(['code' => '0000000000000000']))->getStatusCode() === 422, 'Wrong code must fail.');
    gameCheck($api->redeem($couponSpin['id'], gameRequest(['code' => $couponSpin['couponCode']]))->getStatusCode() === 200, 'Coupon redemption must succeed.');
    gameCheck($api->redeem($couponSpin['id'], gameRequest(['code' => $couponSpin['couponCode']]))->getStatusCode() === 409, 'Coupon cannot be redeemed twice.');
    echo "Prize game integration: OK\n";
} finally {
    $em->rollback();
    $em->close();
    $kernel->shutdown();
}
