<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$tenant = (new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']))->get();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$hasher = new Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher(
    new Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory([
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class => ['algorithm' => 'bcrypt', 'cost' => 4],
    ]),
);
$memberships = $em->getRepository(App\Entity\TenantMembership::class);
$refreshTokens = $kernel->getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
$deletion = new App\Service\CustomerAccountDeletionService(
    $db,
    $refreshTokens,
    new App\Service\UsernameReservation($db),
    new Psr\Log\NullLogger(),
    dirname(__DIR__),
);
$controller = new App\Controller\ApiCustomerAccountDeletionController(
    $security,
    new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']),
    $memberships,
    $hasher,
    $deletion,
);

function deletionCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function deletionRequest(string $password): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/profile', 'DELETE', [], [], [], ['CONTENT_TYPE' => 'application/json'],
        json_encode(['password' => $password], JSON_THROW_ON_ERROR),
    );
}

$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $otherTenant = new App\Entity\Tenant('Other test pharmacy', 'account-delete-'.$suffix);
    $em->persist($otherTenant);
    $resource = new App\Entity\AppointmentResource($tenant, 'Test', 'person');
    $type = new App\Entity\AppointmentType($tenant, 'Test', null, 30);
    $coupon = new App\Entity\Coupon($tenant, 'Test', 'Test', 'Test', '', true, null, null);
    $em->persist($resource);
    $em->persist($type);
    $em->persist($coupon);

    $customer = new App\Entity\User('deleted-'.$suffix, 'deleted-'.$suffix.'@example.invalid', 'Deletion Test');
    $customer->setPassword($hasher->hashPassword($customer, 'correct-password'));
    $customer->setProfileImagePath('/uploads/profiles/nonexistent-'.$suffix.'.jpg');
    $em->persist($customer);
    $em->persist(new App\Entity\TenantMembership($tenant, $customer, ['ROLE_CUSTOMER']));
    $relative = new App\Entity\User('relative-'.$suffix, 'relative-'.$suffix.'@example.invalid', 'Relative');
    $relative->setPassword($hasher->hashPassword($relative, 'correct-password'));
    $em->persist($relative);
    $em->persist(new App\Entity\TenantMembership($tenant, $relative, ['ROLE_CUSTOMER']));
    $em->flush();
    $customerId = $customer->getId();
    $relativeId = $relative->getId();
    $account = new App\Entity\PointAccount($tenant, $customer);
    $transaction = new App\Entity\PointTransaction($account, 20, 'manual', 'Test');
    $em->persist($account);
    $em->persist($transaction);
    $em->persist(new App\Entity\ActiveRedemption($account, 10, 'Test'));
    $em->persist(new App\Entity\LoyaltyReceiptRedemption($tenant, $account, $transaction, hash('sha256', $suffix), 'test-'.$suffix, new DateTimeImmutable(), 100, 20));
    $conversation = new App\Entity\ChatConversation($tenant, $customer, 'v1');
    $message = new App\Entity\ChatMessage($conversation, $customer, 'customer', $suffix);
    $message->encryptedText = 'encrypted-test';
    $em->persist($conversation);
    $em->persist($message);
    $appointment = new App\Entity\Appointment($tenant, $resource, $type, $customer, new DateTimeImmutable('+1 day'), new DateTimeImmutable('+1 day +30 minutes'), 'private note');
    $em->persist($appointment);
    $em->persist(new App\Entity\AppointmentCancellationNotice($appointment));
    $em->persist(new App\Entity\CouponRedemption($tenant, $coupon, $customer, str_pad($suffix, 32, '0')));
    $em->persist(new App\Entity\FamilyConnection($tenant, $customer, $relative, hash('sha256', 'family-'.$suffix)));
    $em->persist(new App\Entity\Medication($tenant, $customer));
    $em->persist(new App\Entity\AppAnalyticsEvent($tenant, $customer, 'view_item'));
    $em->persist(new App\Entity\WebPushSubscription($tenant, $customer, hash('sha256', 'push-'.$suffix), 'encrypted-test'));
    $em->persist(new App\Entity\MediaAsset($tenant, $customer, '/uploads/media/nonexistent-'.$suffix.'.jpg', 'test.jpg', 'private', 'customer', 1, 1, 1));
    $em->flush();
    $accountId = $account->getId();
    $conversationId = $conversation->id;
    $appointmentId = $appointment->getId();
    $refreshValue = bin2hex(random_bytes(32));
    $refreshTokens->save(App\Entity\RefreshToken::createForUserWithTtl($refreshValue, $customer, 3600));

    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($customer, 'api', ['ROLE_USER']));
    deletionCheck($controller(deletionRequest('wrong'))->getStatusCode() === 422, 'Wrong password must not delete account.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM app_user WHERE id = ?', [$customerId]), 'Account must survive invalid password.');
    deletionCheck($controller(deletionRequest('correct-password'))->getStatusCode() === 204, 'Customer account deletion must succeed.');
    deletionCheck($refreshTokens->get($refreshValue) === null, 'Customer refresh sessions must be revoked.');
    deletionCheck((new App\Service\UsernameReservation($db))->isReserved('deleted-'.$suffix), 'Deleted username must not be reassigned while old JWTs can still be valid.');
    foreach (['app_user' => 'id', 'tenant_membership' => 'user_id', 'point_account' => 'owner_id', 'appointment' => 'customer_id', 'chat_conversation' => 'customer_id', 'chat_message' => 'sender_id', 'coupon_redemption' => 'customer_id', 'medication' => 'user_id', 'app_analytics_event' => 'user_id', 'web_push_subscription' => 'user_id', 'media_asset' => 'owner_id'] as $table => $column) {
        deletionCheck($db->fetchOne("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$customerId]) === false, "{$table} must not retain the deleted customer.");
    }
    foreach (['point_transaction', 'active_redemption', 'loyalty_receipt_redemption'] as $table) {
        deletionCheck($db->fetchOne("SELECT 1 FROM {$table} WHERE account_id = ? LIMIT 1", [$accountId]) === false, "{$table} must be deleted with the point account.");
    }
    deletionCheck($db->fetchOne('SELECT 1 FROM chat_message WHERE conversation_id = ?', [$conversationId]) === false, 'Chat messages must be deleted with their conversation.');
    deletionCheck($db->fetchOne('SELECT 1 FROM appointment_cancellation_notice WHERE appointment_id = ?', [$appointmentId]) === false, 'Appointment notices must be deleted with the appointment.');
    deletionCheck($db->fetchOne('SELECT 1 FROM family_connection WHERE participant_one_id = ? OR participant_two_id = ?', [$customerId, $customerId]) === false, 'Family connections must be removed.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM app_user WHERE id = ?', [$relativeId]), 'The other family member must survive.');

    $sharedUser = new App\Entity\User('shared-'.$suffix, 'shared-'.$suffix.'@example.invalid', 'Shared user');
    $sharedUser->setPassword($hasher->hashPassword($sharedUser, 'correct-password'));
    $em->persist($sharedUser);
    $em->persist(new App\Entity\TenantMembership($tenant, $sharedUser, ['ROLE_CUSTOMER', 'ROLE_TENANT_STAFF']));
    $em->persist(new App\Entity\TenantMembership($otherTenant, $sharedUser, ['ROLE_CUSTOMER']));
    $em->flush();
    $sharedId = $sharedUser->getId();
    $otherAccount = new App\Entity\PointAccount($otherTenant, $sharedUser);
    $currentAccount = new App\Entity\PointAccount($tenant, $sharedUser);
    $em->persist($otherAccount);
    $em->persist($currentAccount);
    $em->persist(new App\Entity\PointTransaction($otherAccount, 30, 'manual', 'Other tenant'));
    $em->persist(new App\Entity\PointTransaction($currentAccount, 40, 'manual', 'Current tenant'));
    $em->persist(new App\Entity\PasswordResetToken($sharedUser, hash('sha256', 'reset-'.$suffix), new DateTimeImmutable('+1 hour')));
    $em->persist(new App\Entity\EmailChangeToken($sharedUser, $tenant, 'changed-'.$suffix.'@example.invalid', hash('sha256', 'email-'.$suffix)));
    $staffAsset = new App\Entity\MediaAsset($tenant, $sharedUser, '/uploads/media/staff-'.$suffix.'.jpg', 'staff.jpg', 'public', 'library', 1, 1, 1);
    $em->persist($staffAsset);
    $em->flush();
    $staffAssetId = $staffAsset->getId();
    $sharedRefreshValue = bin2hex(random_bytes(32));
    $refreshTokens->save(App\Entity\RefreshToken::createForUserWithTtl($sharedRefreshValue, $sharedUser, 3600));
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($sharedUser, 'api', ['ROLE_USER']));
    deletionCheck($controller(deletionRequest('correct-password'))->getStatusCode() === 204, 'Tenant customer role deletion must succeed.');
    deletionCheck($refreshTokens->get($sharedRefreshValue) === null, 'Other roles survive, but old refresh sessions must be revoked.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM app_user WHERE id = ?', [$sharedId]), 'Global user must survive other roles and tenants.');
    deletionCheck($db->fetchOne('SELECT roles FROM tenant_membership WHERE tenant_id = ? AND user_id = ?', [$tenant->getId(), $sharedId]) === '["ROLE_TENANT_STAFF"]', 'Staff role must survive without customer role.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM tenant_membership WHERE tenant_id = ? AND user_id = ?', [$otherTenant->getId(), $sharedId]), 'Other tenant membership must survive.');
    deletionCheck($db->fetchOne('SELECT 1 FROM point_account WHERE tenant_id = ? AND owner_id = ?', [$tenant->getId(), $sharedId]) === false, 'Current tenant points must be deleted.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM point_account WHERE tenant_id = ? AND owner_id = ?', [$otherTenant->getId(), $sharedId]), 'Other tenant points must survive.');
    deletionCheck($db->fetchOne('SELECT 1 FROM password_reset_token WHERE user_id = ?', [$sharedId]) === false, 'Pending password resets must be invalidated.');
    deletionCheck($db->fetchOne('SELECT 1 FROM email_change_token WHERE tenant_id = ? AND user_id = ?', [$tenant->getId(), $sharedId]) === false, 'Pending email changes for this tenant must be invalidated.');
    deletionCheck((bool) $db->fetchOne('SELECT 1 FROM media_asset WHERE id = ? AND owner_id = ?', [$staffAssetId, $sharedId]), 'Public staff media must survive customer-role deletion.');
    $em->clear();
    $reloaded = $em->getRepository(App\Entity\User::class)->find($sharedId);
    deletionCheck(!$memberships->hasCustomerMembershipFor($reloaded, $tenant), 'Staff-only membership must not grant customer access.');

    echo "Customer account deletion integration checks passed.\n";
} finally {
    $db->rollBack();
    $kernel->shutdown();
}
