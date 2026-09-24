<?php

declare(strict_types=1);

/*
 * Admin password-reset regression test. Mail is captured in memory and all
 * database writes are rolled back, so no account or token survives the test.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$users = $entityManager->getRepository(App\Entity\User::class);
$memberships = $entityManager->getRepository(App\Entity\TenantMembership::class);
$tokens = $entityManager->getRepository(App\Entity\PasswordResetToken::class);
$refreshTokens = $kernel->getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
$transport = new class extends Symfony\Component\Mailer\Transport\AbstractTransport {
    /** @var list<Symfony\Component\Mailer\SentMessage> */
    public array $messages = [];

    protected function doSend(Symfony\Component\Mailer\SentMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function __toString(): string
    {
        return 'test://memory';
    }
};
$mailer = new App\Service\TenantMailer(
    new Symfony\Component\Mailer\Mailer($transport),
    'test-secret',
    new Psr\Log\NullLogger(),
);
$hasher = new Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher(
    new Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory([
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class => ['algorithm' => 'bcrypt', 'cost' => 4],
    ]),
);
$controller = new App\Controller\ApiPasswordResetController('https://app.test.local', 'https://admin.test.local', 'test@example.invalid');

function resetRequest(string $email): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/auth/password-reset/request',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['email' => $email], JSON_THROW_ON_ERROR),
    );
}

function resetCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$entityManager->beginTransaction();
try {
    $tenant = new App\Entity\Tenant('Reset Test Pharmacy', 'reset-test-' . bin2hex(random_bytes(6)));
    $entityManager->persist($tenant);
    $entityManager->flush();
    $tenantProvider = new App\Service\ActiveTenantProvider($entityManager, $tenant->getSlug());
    $suffix = bin2hex(random_bytes(8));
    $admin = new App\Entity\User('reset-admin-' . $suffix, 'reset-admin-' . $suffix . '@example.invalid', 'Reset Admin');
    $admin->setPassword('old-hash');
    $customer = new App\Entity\User('reset-customer-' . $suffix, 'reset-customer-' . $suffix . '@example.invalid', 'Reset Customer');
    $customer->setPassword('old-hash');
    $entityManager->persist($admin);
    $entityManager->persist($customer);
    $adminMembership = new App\Entity\TenantMembership($tenant, $admin, ['ROLE_TENANT_STAFF']);
    $entityManager->persist($adminMembership);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $customer, ['ROLE_CUSTOMER']));
    $entityManager->flush();

    foreach ([$customer->getEmail(), 'missing-' . $suffix . '@example.invalid'] as $email) {
        $response = $controller->requestAdminReset(resetRequest($email), $users, $memberships, $tenantProvider, $entityManager, $mailer, $tokens);
        resetCheck($response->getStatusCode() === 202, 'Unknown and customer-only accounts must receive a generic response.');
    }
    resetCheck(count($transport->messages) === 0, 'A customer-only or unknown account must not receive an admin reset mail.');

    $response = $controller->requestAdminReset(resetRequest($admin->getEmail()), $users, $memberships, $tenantProvider, $entityManager, $mailer, $tokens);
    resetCheck($response->getStatusCode() === 202 && count($transport->messages) === 1, 'An active staff account must receive exactly one reset mail.');
    $email = $transport->messages[0]->getOriginalMessage();
    resetCheck($email instanceof Symfony\Component\Mime\Email, 'The reset mail must be an Email.');
    resetCheck((bool) preg_match('#https://admin\.test\.local/passwort-zuruecksetzen\?token=([a-f0-9]{64})#', $email->getTextBody() ?? '', $matches), 'The reset mail must point to the admin portal.');

    $controller->requestAdminReset(resetRequest($admin->getEmail()), $users, $memberships, $tenantProvider, $entityManager, $mailer, $tokens);
    resetCheck(count($transport->messages) === 1, 'A repeat request inside one minute must not send another mail.');

    $refreshValue = bin2hex(random_bytes(32));
    $refreshTokens->save(App\Entity\RefreshToken::createForUserWithTtl($refreshValue, $admin, 3600));
    resetCheck($refreshTokens->get($refreshValue) !== null, 'The test refresh session must exist before the reset.');

    $confirmation = Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/auth/password-reset/confirm',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['token' => $matches[1], 'password' => 'a-new-password-123'], JSON_THROW_ON_ERROR),
    );
    $response = $controller->confirmAdminReset($confirmation, $tokens, $entityManager, $hasher, $refreshTokens, $memberships, $tenantProvider);
    resetCheck($response->getStatusCode() === 204, 'The first use of a valid token must succeed.');
    resetCheck($hasher->isPasswordValid($admin, 'a-new-password-123'), 'The new password must be stored as a valid hash.');
    resetCheck($refreshTokens->get($refreshValue) === null, 'A password reset must revoke existing refresh sessions.');
    $response = $controller->confirmAdminReset($confirmation, $tokens, $entityManager, $hasher, $refreshTokens, $memberships, $tenantProvider);
    resetCheck($response->getStatusCode() === 422, 'A reset token must not be reusable.');

    $revokedValue = bin2hex(random_bytes(32));
    $entityManager->persist(new App\Entity\PasswordResetToken($admin, hash('sha256', $revokedValue), new DateTimeImmutable('+60 minutes')));
    $adminMembership->setRoles(['ROLE_CUSTOMER']);
    $entityManager->flush();
    $revokedConfirmation = Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/auth/password-reset/confirm',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['token' => $revokedValue, 'password' => 'another-password-123'], JSON_THROW_ON_ERROR),
    );
    $response = $controller->confirmAdminReset($revokedConfirmation, $tokens, $entityManager, $hasher, $refreshTokens, $memberships, $tenantProvider);
    resetCheck($response->getStatusCode() === 422, 'A removed staff role must invalidate an outstanding admin reset link.');
    resetCheck($hasher->isPasswordValid($admin, 'a-new-password-123'), 'A rejected admin link must not change the password.');

    echo "Admin password reset: OK\n";
} finally {
    $entityManager->rollback();
    $kernel->shutdown();
}
