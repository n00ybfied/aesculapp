<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$tenantProvider = new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']);
$tenant = $tenantProvider->get();
$invitations = $entityManager->getRepository(App\Entity\StaffInvitation::class);
$memberships = $entityManager->getRepository(App\Entity\TenantMembership::class);
$users = $entityManager->getRepository(App\Entity\User::class);
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$container = new Symfony\Component\DependencyInjection\Container();
$container->set('security.token_storage', $storage);
$controller = new App\Controller\ApiAdminUserController(
    new Symfony\Bundle\SecurityBundle\Security($container),
    $tenantProvider,
    $memberships,
    $invitations,
    $users,
    $entityManager,
    new App\Service\TenantMailer(
        new Symfony\Component\Mailer\Mailer(new Symfony\Component\Mailer\Transport\NullTransport()),
        'test-secret',
        new Psr\Log\NullLogger(),
        'null://null',
    ),
    new App\Service\AdminAreaPermissions(),
    'https://admin.example.invalid',
    'test@example.invalid',
);

$entityManager->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $admin = new App\Entity\User('admin-resend-'.$suffix, 'admin-resend-'.$suffix.'@example.invalid', 'Admin');
    $staff = new App\Entity\User('staff-resend-'.$suffix, 'staff-resend-'.$suffix.'@example.invalid', 'Staff');
    $admin->setPassword('test-hash');
    $staff->setPassword('test-hash');
    $entityManager->persist($admin);
    $entityManager->persist($staff);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $admin, ['ROLE_TENANT_ADMIN']));
    $staffMembership = new App\Entity\TenantMembership($tenant, $staff, ['ROLE_TENANT_STAFF']);
    $staffMembership->setPermissions(['users']);
    $entityManager->persist($staffMembership);
    $oldToken = bin2hex(random_bytes(32));
    $invitation = new App\Entity\StaffInvitation($tenant, 'invite-'.$suffix.'@example.invalid', 'Invitee', ['ROLE_TENANT_STAFF'], hash('sha256', $oldToken));
    $invitation->setPermissions(['users']);
    (new ReflectionProperty(App\Entity\StaffInvitation::class, 'expiresAt'))->setValue($invitation, new DateTimeImmutable('-1 day'));
    $entityManager->persist($invitation);
    $entityManager->flush();

    if (!in_array($invitation, $invitations->findPendingForTenant($tenant), true)) {
        throw new RuntimeException('Expired but unaccepted invitations must stay visible.');
    }

    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($admin, 'api', ['ROLE_TENANT_ADMIN']));
    $resend = $controller->resendInvitation($invitation->getId());
    if ($resend->getStatusCode() !== 200 || $invitations->findUsableByTokenHash(hash('sha256', $oldToken)) !== null
        || $invitation->getExpiresAt() <= new DateTimeImmutable('+6 days')
        || count($invitations->findPendingForTenant($tenant)) < 1) {
        throw new RuntimeException('Resending must invalidate the previous token and retain the open invitation.');
    }

    $staffInvitation = new App\Entity\StaffInvitation($tenant, 'staff-invite-'.$suffix.'@example.invalid', 'Staff invitee', ['ROLE_TENANT_STAFF'], hash('sha256', bin2hex(random_bytes(32))));
    $staffInvitation->setPermissions(['settings']);
    $adminInvitation = new App\Entity\StaffInvitation($tenant, 'admin-invite-'.$suffix.'@example.invalid', 'Admin invitee', ['ROLE_TENANT_ADMIN'], hash('sha256', bin2hex(random_bytes(32))));
    $entityManager->persist($staffInvitation);
    $entityManager->persist($adminInvitation);
    $entityManager->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($staff, 'api', ['ROLE_TENANT_STAFF']));
    if ($controller->resendInvitation($staffInvitation->getId())->getStatusCode() !== 403
        || $controller->resendInvitation($adminInvitation->getId())->getStatusCode() !== 403
        || $controller->resendInvitation($invitation->getId())->getStatusCode() !== 200) {
        throw new RuntimeException('Staff may only resend invitations within their own permissions.');
    }

    $invitation->markAccepted();
    $entityManager->flush();
    if ($controller->resendInvitation($invitation->getId())->getStatusCode() !== 422
        || in_array($invitation, $invitations->findPendingForTenant($tenant), true)) {
        throw new RuntimeException('Accepted invitations must not be resent or listed.');
    }
    $otherTenant = new App\Entity\Tenant('Other Pharmacy', 'other-resend-'.$suffix);
    $entityManager->persist($otherTenant);
    $foreignInvitation = new App\Entity\StaffInvitation($otherTenant, 'foreign-'.$suffix.'@example.invalid', 'Foreign', ['ROLE_TENANT_STAFF'], hash('sha256', bin2hex(random_bytes(32))));
    $entityManager->persist($foreignInvitation);
    $entityManager->flush();
    if ($controller->resendInvitation($foreignInvitation->getId())->getStatusCode() !== 404) {
        throw new RuntimeException('Invitations from another tenant must not be resent.');
    }

    echo "Staff invitation resend checks passed.\n";
} finally {
    $entityManager->getConnection()->rollBack();
    $entityManager->close();
    $kernel->shutdown();
}
