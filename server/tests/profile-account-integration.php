<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$tenantProvider = new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']);
$tenant = $tenantProvider->get();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$memberships = $em->getRepository(App\Entity\TenantMembership::class);
$passwordHasher = new Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher(
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
$refreshTokens = $kernel->getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
$reservation = new App\Service\UsernameReservation($em->getConnection());
$push = new App\Service\ChatPushService(
    $em,
    new App\Service\ChatCipher(new App\Service\PrivateDataKeyPath(dirname(__DIR__))),
    new Psr\Log\NullLogger(),
    $memberships,
    dirname(__DIR__),
);
$profiles = new App\Controller\ApiProfileController(
    $security, $tenantProvider, $memberships, $em, new App\Service\ImageProcessor(), $push,
    $reservation, $passwordHasher, $refreshTokens,
);
$emailChanges = new App\Controller\ApiEmailChangeController(
    $security, $tenantProvider, $memberships, $em, $passwordHasher, $mailer, $refreshTokens,
    'https://example.invalid', 'noreply@example.invalid',
);

function accountRequest(array $data): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/profile', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
        json_encode($data, JSON_THROW_ON_ERROR),
    );
}

function accountCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$em->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $oldUsername = 'old-'.$suffix.'@example.invalid';
    $user = new App\Entity\User($oldUsername, $oldUsername, 'Testkunde');
    $user->setPassword($passwordHasher->hashPassword($user, 'correct-password'));
    $em->persist($user);
    $em->persist(new App\Entity\TenantMembership($tenant, $user, ['ROLE_CUSTOMER']));
    $em->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', ['ROLE_CUSTOMER']));

    $profile = json_decode((string) $profiles->get(accountRequest([]))->getContent(), true, 512, JSON_THROW_ON_ERROR)['profile'];
    foreach (['phone', 'streetAddress', 'postalCode', 'city', 'birthDate'] as $field) {
        $profile[$field] ??= '';
    }
    $newUsername = 'new-'.$suffix;
    $invalid = $profiles->update(accountRequest([...$profile, 'username' => $newUsername, 'usernamePassword' => 'wrong']));
    accountCheck($invalid->getStatusCode() === 422, 'Username change must require the current password.');
    $updated = $profiles->update(accountRequest([...$profile, 'username' => $newUsername, 'usernamePassword' => 'correct-password']));
    accountCheck($updated->getStatusCode() === 200 && $user->getUsername() === $newUsername, 'Username must change after password verification.');
    accountCheck($reservation->isReserved($oldUsername), 'Former username must be reserved until old JWTs expire.');

    $newEmail = 'new-'.$suffix.'@example.invalid';
    accountCheck($emailChanges->requestChange(accountRequest(['email' => $newEmail, 'password' => 'wrong']))->getStatusCode() === 422, 'Email change must require the current password.');
    accountCheck($emailChanges->requestChange(accountRequest(['email' => $newEmail, 'password' => 'correct-password']))->getStatusCode() === 200, 'Valid email change request must be accepted.');
    accountCheck($user->getEmail() === $oldUsername, 'Email must not change before link confirmation.');

    $rawToken = bin2hex(random_bytes(32));
    $em->persist(new App\Entity\EmailChangeToken($user, $tenant, $newEmail, hash('sha256', $rawToken)));
    $em->flush();
    accountCheck($emailChanges->confirmChange(accountRequest(['token' => $rawToken]))->getStatusCode() === 200, 'A valid token must confirm the new email.');
    accountCheck($user->getEmail() === $newEmail, 'Confirmed email must be stored.');
    accountCheck($emailChanges->confirmChange(accountRequest(['token' => $rawToken]))->getStatusCode() === 422, 'Email change token must be single use.');

    echo "Profile account integration checks passed.\n";
} finally {
    $em->getConnection()->rollBack();
    $kernel->shutdown();
}
