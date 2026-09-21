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
$memberships = $em->getRepository(App\Entity\TenantMembership::class);
$provider = new App\Service\ActiveTenantProvider($em, (string) $_ENV['APP_TENANT_SLUG']);
$controller = new App\Controller\ApiCouponRedemptionController($em, $security, $provider, $memberships);
$catalog = new App\Controller\ApiCouponController($em, $provider, $memberships, $security, new App\Service\RichTextSanitizer());

function couponCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function couponLogin($storage, App\Entity\User $user, array $roles): void
{
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', $roles));
}

function couponBasketRequest(array $couponIds): Symfony\Component\HttpFoundation\Request
{
    return new Symfony\Component\HttpFoundation\Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['couponIds' => $couponIds], JSON_THROW_ON_ERROR));
}

$em->beginTransaction();
try {
    $tenant = $provider->get();
    $foreign = new App\Entity\Tenant('Other pharmacy', 'coupon-test-'.bin2hex(random_bytes(4)));
    $em->persist($foreign);
    $users = [];
    foreach (['customer', 'other', 'staff', 'foreign'] as $name) {
        $email = 'coupon-'.$name.'-'.bin2hex(random_bytes(5)).'@example.invalid';
        $user = new App\Entity\User($email, $email, ucfirst($name));
        $user->setPassword('not-a-login');
        $userTenant = $name === 'foreign' ? $foreign : $tenant;
        $roles = $name === 'staff' ? ['ROLE_TENANT_STAFF'] : ['ROLE_CUSTOMER'];
        $em->persist($user);
        $em->persist(new App\Entity\TenantMembership($userTenant, $user, $roles));
        $users[$name] = $user;
    }
    $coupon = new App\Entity\Coupon($tenant, 'Test coupon', 'One use', '<p>Test</p>', '', true, null, null);
    $secondCoupon = new App\Entity\Coupon($tenant, 'Second coupon', 'One use', '<p>Test</p>', '', true, null, null);
    $deletableCoupon = new App\Entity\Coupon($tenant, 'Deletable coupon', 'Admin test', '<p>Test</p>', '', true, null, null);
    $foreignCoupon = new App\Entity\Coupon($foreign, 'Foreign coupon', 'Hidden', '<p>Test</p>', '', true, null, null);
    $em->persist($coupon);
    $em->persist($secondCoupon);
    $em->persist($deletableCoupon);
    $em->persist($foreignCoupon);
    $em->flush();

    $storage->setToken(null);
    couponCheck($controller->redeem(couponBasketRequest([$coupon->getId()]))->getStatusCode() === 401, 'Unauthenticated redemption must fail.');
    couponCheck($catalog->customer(new Symfony\Component\HttpFoundation\Request())->getStatusCode() === 401, 'Coupon status must not be public.');

    couponLogin($storage, $users['customer'], ['ROLE_CUSTOMER']);
    couponCheck($controller->redeem(couponBasketRequest([$foreignCoupon->getId()]))->getStatusCode() === 404, 'Foreign coupon must not be redeemable.');
    $first = $controller->redeem(couponBasketRequest([$coupon->getId(), $secondCoupon->getId()]));
    couponCheck($first->getStatusCode() === 201, 'Coupon basket redemption must succeed.');
    $firstId = json_decode((string) $first->getContent(), true, 512, JSON_THROW_ON_ERROR)['redemption']['id'];
    couponCheck(count(json_decode((string) $first->getContent(), true, 512, JSON_THROW_ON_ERROR)['redemption']['items']) === 2, 'Basket must retain both coupons.');
    couponCheck($controller->redeem(couponBasketRequest([$coupon->getId()]))->getStatusCode() === 409, 'A second redemption must fail.');
    $coupon->update('Test coupon', 'One use', '<p>Test</p>', '', true, null, new DateTimeImmutable('-1 day'));
    $em->flush();
    $list = json_decode((string) $catalog->customer(new Symfony\Component\HttpFoundation\Request())->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $listed = array_values(array_filter($list['coupons'], static fn (array $item): bool => $item['id'] === $coupon->getId()));
    couponCheck(count($listed) === 1 && $listed[0]['isRedeemed'] === true, 'Customer list must mark redeemed coupon.');
    couponCheck(json_decode((string) $controller->customerActive(new Symfony\Component\HttpFoundation\Request())->getContent(), true, 512, JSON_THROW_ON_ERROR)['redemption']['id'] === $firstId, 'Customer must see the active basket presentation.');
    $coupon->update('Test coupon', 'One use', '<p>Test</p>', '', true, null, null);
    $em->flush();

    couponLogin($storage, $users['other'], ['ROLE_CUSTOMER']);
    couponCheck($controller->redeem(couponBasketRequest([$coupon->getId()]))->getStatusCode() === 201, 'Another customer may redeem independently.');

    couponLogin($storage, $users['foreign'], ['ROLE_CUSTOMER']);
    couponCheck($controller->redeem(couponBasketRequest([$coupon->getId()]))->getStatusCode() === 401, 'Foreign tenant customer must not redeem.');

    couponLogin($storage, $users['staff'], ['ROLE_TENANT_STAFF']);
    couponCheck($controller->cancel($firstId)->getStatusCode() === 204, 'Staff must be able to cancel an active redemption.');
    couponCheck($controller->cancel($firstId)->getStatusCode() === 404, 'Cancellation must be single-use.');
    $deletableCouponId = $deletableCoupon->getId();
    couponCheck($catalog->delete($deletableCouponId)->getStatusCode() === 204, 'Staff must be able to delete a coupon.');
    couponCheck($em->getRepository(App\Entity\Coupon::class)->find($deletableCouponId) === null, 'Deleted coupon must no longer exist.');

    couponLogin($storage, $users['customer'], ['ROLE_CUSTOMER']);
    couponCheck($controller->redeem(couponBasketRequest([$coupon->getId()]))->getStatusCode() === 201, 'Cancelled coupon must become available again.');
    echo "Coupon integration: OK\n";
} finally {
    $em->rollback();
    $em->close();
    $kernel->shutdown();
}
