<?php

declare(strict_types=1);

/*
 * Authorization regression test. It exercises attempts to use known IDs from
 * another customer or tenant, and proves that a forged token role cannot
 * replace the server-side TenantMembership check. The outer transaction is
 * always rolled back, so no tenants, users or private data remain.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$memberships = $entityManager->getRepository(App\Entity\TenantMembership::class);
$tenantA = new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']);
$cipher = new App\Service\ChatCipher(new App\Service\PrivateDataKeyPath(dirname(__DIR__)));

function authorizationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectsStatus(callable $call, int $expected): void
{
    try {
        $response = $call();
        authorizationCheck($response->getStatusCode() === $expected, sprintf('Expected HTTP %d, got HTTP %d.', $expected, $response->getStatusCode()));
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        authorizationCheck($exception->getStatusCode() === $expected, sprintf('Expected HTTP %d, got exception HTTP %d.', $expected, $exception->getStatusCode()));
    }
}

/** @param list<string> $tokenRoles */
function authenticate(Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage $storage, App\Entity\User $user, array $tokenRoles): void
{
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', $tokenRoles));
}

function createUser(Doctrine\ORM\EntityManagerInterface $entityManager, App\Entity\Tenant $tenant, string $displayName, array $roles): App\Entity\User
{
    $email = 'authorization-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $user = new App\Entity\User($email, $email, $displayName);
    $user->setPassword('not-a-login');
    $entityManager->persist($user);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $user, $roles));

    return $user;
}

$entityManager->beginTransaction();
try {
    $activeTenant = $tenantA->get();
    $foreignTenant = new App\Entity\Tenant('Authorization Test Pharmacy', 'authorization-test-' . bin2hex(random_bytes(5)));
    $entityManager->persist($foreignTenant);
    $entityManager->flush();

    $tenantB = new App\Service\ActiveTenantProvider($entityManager, $foreignTenant->getSlug());
    $ownerA = createUser($entityManager, $activeTenant, 'Owner A', ['ROLE_CUSTOMER']);
    $otherCustomerA = createUser($entityManager, $activeTenant, 'Other Customer A', ['ROLE_CUSTOMER']);
    $staffA = createUser($entityManager, $activeTenant, 'Staff A', ['ROLE_TENANT_STAFF']);
    $customerB = createUser($entityManager, $foreignTenant, 'Customer B', ['ROLE_CUSTOMER']);
    $staffB = createUser($entityManager, $foreignTenant, 'Staff B', ['ROLE_TENANT_STAFF']);
    $rewardB = new App\Entity\Reward($foreignTenant, 'Foreign reward', 'Private tenant data', '<p>Never visible in tenant A.</p>', '', 100, true);
    $entityManager->persist($rewardB);
    $entityManager->flush();

    $medicationsA = new App\Controller\ApiMedicationController($security, $tenantA, $memberships, $entityManager, $cipher);
    $medicationsB = new App\Controller\ApiMedicationController($security, $tenantB, $memberships, $entityManager, $cipher);
    $rewardsA = new App\Controller\ApiRewardController($tenantA, $memberships, $security, $entityManager, new App\Service\ImageProcessor(), new App\Service\RichTextSanitizer());
    $rewardsB = new App\Controller\ApiRewardController($tenantB, $memberships, $security, $entityManager, new App\Service\ImageProcessor(), new App\Service\RichTextSanitizer());
    $customersA = new App\Controller\ApiAdminCustomerController($security, $tenantA, $memberships, $entityManager, new App\Service\PointAccountProvisioner($entityManager));
    $brandingA = new App\Controller\ApiAdminBrandingController($tenantA, $memberships, $security, $entityManager, (string) ($_ENV['APP_SECRET'] ?? 'authorization-test-secret'));

    // Unauthenticated calls never reveal a medication plan.
    $storage->setToken(null);
    expectsStatus(fn () => $medicationsA->list(new Symfony\Component\HttpFoundation\Request()), 403);

    authenticate($storage, $ownerA, ['ROLE_CUSTOMER']);
    $created = $medicationsA->create(new Symfony\Component\HttpFoundation\Request([], [
        'name' => 'Private medication', 'dosage' => '500 mg', 'schedule' => '1-0-0-1', 'notes' => 'Owner A only', 'refillDate' => '',
    ]));
    authorizationCheck($created->getStatusCode() === 201, 'Owner must be able to create their own medication.');
    $medicationId = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR)['medication']['id'];
    $storedName = $entityManager->getConnection()->fetchOne('SELECT encrypted_name FROM medication WHERE id = ?', [$medicationId]);
    authorizationCheck(is_string($storedName) && str_starts_with($storedName, 'v1:') && !str_contains($storedName, 'Private medication'), 'Private medication name must remain encrypted at rest.');

    // A customer knowing another customer's ID can neither read, update nor delete it.
    authenticate($storage, $otherCustomerA, ['ROLE_CUSTOMER']);
    $list = json_decode((string) $medicationsA->list(new Symfony\Component\HttpFoundation\Request())->getContent(), true, 512, JSON_THROW_ON_ERROR);
    authorizationCheck($list['medications'] === [], 'Customer must not receive another customer\'s medication in a list.');
    expectsStatus(fn () => $medicationsA->update($medicationId, new Symfony\Component\HttpFoundation\Request([], ['name' => 'Stolen', 'schedule' => '1-0-0-1', 'dosage' => '', 'notes' => '', 'refillDate' => ''])), 404);
    expectsStatus(fn () => $medicationsA->delete($medicationId), 404);

    // A confirmed family connection starts with no data access at all. The owner
    // must explicitly grant viewing, then separately grant management.
    $familyConnection = new App\Entity\FamilyConnection($activeTenant, $ownerA, $otherCustomerA, hash('sha256', bin2hex(random_bytes(32))));
    $familyConnection->accept();
    $entityManager->persist($familyConnection);
    $entityManager->flush();
    expectsStatus(fn () => $medicationsA->list(new Symfony\Component\HttpFoundation\Request(['ownerId' => (string) $ownerA->getId()])), 404);
    $familyConnection->setMedicationAccess($ownerA, true, false);
    $entityManager->flush();
    expectsStatus(fn () => $medicationsA->list(new Symfony\Component\HttpFoundation\Request(['ownerId' => (string) $ownerA->getId()])), 200);
    expectsStatus(fn () => $medicationsA->update($medicationId, new Symfony\Component\HttpFoundation\Request([], ['name' => 'Viewers cannot edit', 'schedule' => '1-0-0-1', 'dosage' => '', 'notes' => '', 'refillDate' => ''])), 404);
    $familyConnection->setMedicationAccess($ownerA, true, true);
    $entityManager->flush();
    expectsStatus(fn () => $medicationsA->update($medicationId, new Symfony\Component\HttpFoundation\Request([], ['name' => 'Managed by family', 'schedule' => '1-0-0-1', 'dosage' => '', 'notes' => '', 'refillDate' => ''])), 200);

    // Confirmed point sharing exposes one combined balance and debits both
    // personal ledgers proportionally. A second family member cannot redeem
    // while the first member's five-minute redemption is active.
    $familyConnection->requestPointSharing($ownerA);
    $familyConnection->acceptPointSharing($otherCustomerA);
    $ownerAccount = new App\Entity\PointAccount($activeTenant, $ownerA);
    $otherAccount = new App\Entity\PointAccount($activeTenant, $otherCustomerA);
    $rewardA = new App\Entity\Reward($activeTenant, 'Shared reward', 'Family', '<p>Shared.</p>', '', 100, true);
    $entityManager->persist($ownerAccount);
    $entityManager->persist($otherAccount);
    $entityManager->persist($rewardA);
    $entityManager->flush();
    $entityManager->persist(new App\Entity\PointTransaction($ownerAccount, 100, 'test_credit', 'Owner share'));
    $entityManager->persist(new App\Entity\PointTransaction($otherAccount, 300, 'test_credit', 'Other share'));
    $entityManager->flush();
    $familyPoints = new App\Service\FamilyPointSharingService($entityManager);
    authorizationCheck($familyPoints->combinedBalance($ownerA, $activeTenant) === 400, 'A confirmed family group must expose its combined balance.');
    $roundingDebits = $familyPoints->proportionalDebits([$ownerAccount, $otherAccount], 102);
    authorizationCheck($roundingDebits[$ownerAccount->getId() ?? 0] === 25 && $roundingDebits[$otherAccount->getId() ?? 0] === 77, 'A rounding point must be debited from the account with the largest balance.');
    $redemptions = new App\Controller\ApiRedemptionController($entityManager, $security, $tenantA, $memberships, new App\Service\PointAccountProvisioner($entityManager), $familyPoints);
    authenticate($storage, $ownerA, ['ROLE_CUSTOMER']);
    $redeem = $redemptions->redeem(new Symfony\Component\HttpFoundation\Request(content: json_encode(['selections' => [['rewardId' => $rewardA->getId(), 'quantity' => 1]],], JSON_THROW_ON_ERROR)));
    authorizationCheck($redeem->getStatusCode() === 200, 'A family member must be able to redeem from the combined balance.');
    authorizationCheck(json_decode((string) $redeem->getContent(), true, 512, JSON_THROW_ON_ERROR)['remainingPoints'] === 300, 'The combined balance must be returned after redemption.');
    authorizationCheck($familyPoints->balance($ownerAccount) === 75 && $familyPoints->balance($otherAccount) === 225, 'A family redemption must debit personal accounts proportionally.');
    authenticate($storage, $otherCustomerA, ['ROLE_CUSTOMER']);
    expectsStatus(fn () => $redemptions->redeem(new Symfony\Component\HttpFoundation\Request(content: json_encode(['selections' => [['rewardId' => $rewardA->getId(), 'quantity' => 1]],], JSON_THROW_ON_ERROR))), 409);

    // A legitimate customer from tenant B cannot use an ID from tenant A either.
    authenticate($storage, $customerB, ['ROLE_CUSTOMER']);
    expectsStatus(fn () => $medicationsB->update($medicationId, new Symfony\Component\HttpFoundation\Request([], ['name' => 'Stolen', 'schedule' => '1-0-0-1', 'dosage' => '', 'notes' => '', 'refillDate' => ''])), 404);

    // A forged JWT role is insufficient: the membership still has to carry a staff role.
    authenticate($storage, $ownerA, ['ROLE_TENANT_ADMIN']);
    expectsStatus(fn () => $rewardsA->adminList(new Symfony\Component\HttpFoundation\Request()), 403);
    expectsStatus(fn () => $customersA->list(new Symfony\Component\HttpFoundation\Request()), 403);

    // A real staff membership is scoped to its tenant and cannot enumerate or edit foreign records.
    authenticate($storage, $staffA, ['ROLE_TENANT_STAFF']);
    expectsStatus(fn () => $rewardsA->adminOne($rewardB->getId() ?? 0, new Symfony\Component\HttpFoundation\Request()), 404);
    expectsStatus(fn () => $rewardsA->changeVisibility($rewardB->getId() ?? 0, new Symfony\Component\HttpFoundation\Request(content: json_encode(['isVisible' => false,], JSON_THROW_ON_ERROR))), 404);
    expectsStatus(fn () => $customersA->get($customerB->getId() ?? 0, new Symfony\Component\HttpFoundation\Request()), 404);
    expectsStatus(fn () => $rewardsB->adminList(new Symfony\Component\HttpFoundation\Request()), 403);

    // A tenant may enable family point sharing before any pool exists. Once a
    // pool has been created, the server must refuse to disable that feature.
    expectsStatus(fn () => $brandingA->update(new Symfony\Component\HttpFoundation\Request([], ['familyPointSharingEnabled' => 'true', 'appointmentBookingFutureDays' => '28', 'appointmentCancellationHours' => '24'])), 200);
    authorizationCheck($activeTenant->isFamilyPointSharingEnabled(), 'Tenant staff must be able to enable family point sharing.');
    $activeTenant->lockFamilyPointSharing();
    $entityManager->flush();
    expectsStatus(fn () => $brandingA->update(new Symfony\Component\HttpFoundation\Request([], ['familyPointSharingEnabled' => 'false', 'appointmentBookingFutureDays' => '28', 'appointmentCancellationHours' => '24'])), 422);
    authorizationCheck($activeTenant->isFamilyPointSharingEnabled() && $activeTenant->isFamilyPointSharingLocked(), 'A tenant with an existing family pool must keep point sharing enabled.');

    // The matching tenant staff can access its own tenant's reward, proving the test is not a false negative.
    authenticate($storage, $staffB, ['ROLE_TENANT_STAFF']);
    expectsStatus(fn () => $rewardsB->adminOne($rewardB->getId() ?? 0, new Symfony\Component\HttpFoundation\Request()), 200);

    echo "Authorization checks passed: unauthenticated access, customer ownership, family grants, shared-balance redemption, tenant isolation, token-role forgery, staff privilege boundaries, and locked family point sharing.\n";
} finally {
    while ($entityManager->getConnection()->isTransactionActive()) {
        $entityManager->getConnection()->rollBack();
    }
    $kernel->shutdown();
}
