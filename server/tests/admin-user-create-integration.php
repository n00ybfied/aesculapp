<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$tenant = (new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']))->get();
$memberships = $entityManager->getRepository(App\Entity\TenantMembership::class);
$users = $entityManager->getRepository(App\Entity\User::class);
$resetTokens = $entityManager->getRepository(App\Entity\PasswordResetToken::class);
$refreshTokens = $kernel->getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$container = new Symfony\Component\DependencyInjection\Container();
$container->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($container);
$hasher = new Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher(
    new Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory([
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class => ['algorithm' => 'bcrypt', 'cost' => 4],
    ]),
);
$mailer = new App\Service\TenantMailer(
    new Symfony\Component\Mailer\Mailer(new Symfony\Component\Mailer\Transport\NullTransport()),
    'test-secret',
    new Psr\Log\NullLogger(),
    'null://null',
);
$controller = new App\Controller\ApiAdminUserController(
    $security,
    new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']),
    $memberships,
    $entityManager->getRepository(App\Entity\StaffInvitation::class),
    $users,
    $entityManager,
    $mailer,
    new App\Service\AdminAreaPermissions(),
    'https://admin.example.invalid',
    'test@example.invalid',
);

function userCreateRequest(array $data): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/users', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
        json_encode($data, JSON_THROW_ON_ERROR),
    );
}

$entityManager->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $admin = new App\Entity\User('admin-'.$suffix, 'admin-'.$suffix.'@example.invalid', 'Test Admin');
    $admin->setPassword($hasher->hashPassword($admin, 'TestPassword123'));
    $entityManager->persist($admin);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $admin, ['ROLE_TENANT_ADMIN']));
    $entityManager->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($admin, 'api', ['ROLE_TENANT_ADMIN']));

    $email = 'direct-'.$suffix.'@example.invalid';
    $request = ['displayName' => 'Direkt Mitarbeiter', 'email' => $email, 'password' => 'TestPassword123', 'role' => 'staff'];
    $created = $controller->create(userCreateRequest($request), $hasher, new App\Service\UsernameReservation($entityManager->getConnection()));
    if ($created->getStatusCode() !== 201) throw new RuntimeException('Direct staff creation failed.');
    $user = $users->findOneByEmail($email);
    $membership = $user === null ? null : $memberships->findForUserAndTenant($user, $tenant);
    if ($membership === null || !$user->isActive() || !$hasher->isPasswordValid($user, 'TestPassword123')
        || $membership->getPermissions() !== App\Service\AdminAreaPermissions::DEFAULT_STAFF_AREAS) {
        throw new RuntimeException('Direct account password or default permissions are wrong.');
    }
    $duplicate = $controller->create(userCreateRequest($request), $hasher, new App\Service\UsernameReservation($entityManager->getConnection()));
    if ($duplicate->getStatusCode() !== 409) throw new RuntimeException('Existing accounts must not be overwritten.');

    $passwordRequest = static fn (int $id, string $password): Symfony\Component\HttpFoundation\Request => Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/users/'.$id.'/password', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'],
        json_encode(['password' => $password], JSON_THROW_ON_ERROR),
    );
    $refreshValue = bin2hex(random_bytes(32));
    $refreshTokens->save(App\Entity\RefreshToken::createForUserWithTtl($refreshValue, $user, 3600));
    $resetValue = bin2hex(random_bytes(32));
    $entityManager->persist(new App\Entity\PasswordResetToken($user, hash('sha256', $resetValue), new DateTimeImmutable('+60 minutes')));
    $entityManager->flush();

    $changed = $controller->changePassword($user->getId(), $passwordRequest($user->getId(), 'NewTestPassword123'), $hasher, $refreshTokens, $resetTokens);
    if ($changed->getStatusCode() !== 204 || !$hasher->isPasswordValid($user, 'NewTestPassword123')
        || $hasher->isPasswordValid($user, 'TestPassword123')
        || $refreshTokens->get($refreshValue) !== null
        || $resetTokens->findUsableByTokenHash(hash('sha256', $resetValue)) !== null) {
        throw new RuntimeException('Admin password change must replace the hash and revoke existing reset and refresh tokens.');
    }
    if ($controller->changePassword($user->getId(), $passwordRequest($user->getId(), 'short'), $hasher, $refreshTokens, $resetTokens)->getStatusCode() !== 422) {
        throw new RuntimeException('Short passwords must be rejected.');
    }
    $customer = new App\Entity\User('customer-'.$suffix, 'customer-'.$suffix.'@example.invalid', 'Test Customer');
    $customer->setPassword($hasher->hashPassword($customer, 'CustomerPassword123'));
    $entityManager->persist($customer);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $customer, ['ROLE_CUSTOMER']));
    $entityManager->flush();
    if ($controller->changePassword($customer->getId(), $passwordRequest($customer->getId(), 'NewTestPassword123'), $hasher, $refreshTokens, $resetTokens)->getStatusCode() !== 404) {
        throw new RuntimeException('Admins must not change customer-only passwords through staff management.');
    }
    $otherTenant = new App\Entity\Tenant('Other Pharmacy', 'other-'.$suffix);
    $foreignStaff = new App\Entity\User('foreign-'.$suffix, 'foreign-'.$suffix.'@example.invalid', 'Other Staff');
    $foreignStaff->setPassword($hasher->hashPassword($foreignStaff, 'ForeignPassword123'));
    $entityManager->persist($otherTenant);
    $entityManager->persist($foreignStaff);
    $entityManager->persist(new App\Entity\TenantMembership($otherTenant, $foreignStaff, ['ROLE_TENANT_STAFF']));
    $entityManager->flush();
    if ($controller->changePassword($foreignStaff->getId(), $passwordRequest($foreignStaff->getId(), 'NewTestPassword123'), $hasher, $refreshTokens, $resetTokens)->getStatusCode() !== 404
        || !$hasher->isPasswordValid($foreignStaff, 'ForeignPassword123')) {
        throw new RuntimeException('An administrator must not change a staff password in another tenant.');
    }

    $membership->setPermissions(['users']);
    $entityManager->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', ['ROLE_TENANT_STAFF']));
    $second = $request;
    $second['email'] = 'second-'.$suffix.'@example.invalid';
    $second['permissions'] = ['settings'];
    if ($controller->create(userCreateRequest($second), $hasher, new App\Service\UsernameReservation($entityManager->getConnection()))->getStatusCode() !== 403) {
        throw new RuntimeException('Staff must not grant rights they do not possess.');
    }
    $second['permissions'] = ['users'];
    $second['role'] = 'admin';
    if ($controller->create(userCreateRequest($second), $hasher, new App\Service\UsernameReservation($entityManager->getConnection()))->getStatusCode() !== 403) {
        throw new RuntimeException('Staff must not create administrators.');
    }
    if ($controller->changePassword($admin->getId(), $passwordRequest($admin->getId(), 'AnotherPassword123'), $hasher, $refreshTokens, $resetTokens)->getStatusCode() !== 403
        || !$hasher->isPasswordValid($admin, 'TestPassword123')) {
        throw new RuntimeException('Staff must not change passwords, even with access to user management.');
    }
    echo "Direct staff account checks passed.\n";
} finally {
    $entityManager->getConnection()->rollBack();
    $entityManager->close();
    $kernel->shutdown();
}
