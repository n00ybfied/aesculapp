<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$tenant = (new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']))->get();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$emailLogger = new class extends Psr\Log\AbstractLogger {
    /** @var list<array{message: string, context: array}> */
    public array $entries = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = ['message' => (string) $message, 'context' => $context];
    }
};
$mailer = new App\Service\TenantMailer(
    new Symfony\Component\Mailer\Mailer(new Symfony\Component\Mailer\Transport\NullTransport()),
    'test-secret',
    $emailLogger,
    'null://null',
);
$push = new App\Service\ChatPushService(
    $entityManager,
    new App\Service\ChatCipher(new App\Service\PrivateDataKeyPath(dirname(__DIR__))),
    new Psr\Log\NullLogger(),
    $entityManager->getRepository(App\Entity\TenantMembership::class),
    dirname(__DIR__),
);
$controller = new App\Controller\ApiAdminAppointmentController(
    $entityManager,
    new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']),
    $entityManager->getRepository(App\Entity\TenantMembership::class),
    $security,
    $mailer,
    'test@example.invalid',
    new App\Service\AppointmentBlocker($entityManager),
    $push,
    new Psr\Log\NullLogger(),
    'https://example.invalid',
    new App\Service\AppointmentStaffNotifier($mailer, $emailLogger, 'test@example.invalid', 'https://admin.example.invalid', $entityManager->getRepository(App\Entity\TenantMembership::class)),
    new App\Service\ChatCipher(new App\Service\PrivateDataKeyPath(dirname(__DIR__))),
);

function appointmentRequest(array $data): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/admin/appointments',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($data, JSON_THROW_ON_ERROR),
    );
}

function appointmentCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$entityManager->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $staff = new App\Entity\User('staff-'.$suffix, 'appointment-staff-'.$suffix.'@example.invalid', 'Test Staff');
    $staff->setPassword('not-a-login');
    $otherStaff = new App\Entity\User('other-staff-'.$suffix, 'appointment-other-staff-'.$suffix.'@example.invalid', 'Other Staff');
    $otherStaff->setPassword('not-a-login');
    $customer = new App\Entity\User('customer-'.$suffix, 'appointment-customer-'.$suffix.'@example.invalid', 'Test Customer');
    $customer->setPassword('not-a-login');
    $outsider = new App\Entity\User('outsider-'.$suffix, 'appointment-outsider-'.$suffix.'@example.invalid', 'Other User');
    $outsider->setPassword('not-a-login');
    $entityManager->persist($staff);
    $entityManager->persist($otherStaff);
    $entityManager->persist($customer);
    $entityManager->persist($outsider);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $staff, ['ROLE_TENANT_STAFF']));
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $otherStaff, ['ROLE_TENANT_STAFF']));
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $customer, ['ROLE_CUSTOMER']));
    $type = new App\Entity\AppointmentType($tenant, 'Testberatung', null, 30, 5);
    $resource = new App\Entity\AppointmentResource($tenant, 'Testperson '.$suffix);
    $entityManager->persist($type);
    $entityManager->persist($resource);
    $entityManager->flush();

    // Keep this regression independent of earlier cancellation notices in the local tenant.
    $existingNoticeId = (int) $entityManager->getConnection()->fetchOne('SELECT COALESCE(MAX(id), 0) FROM appointment_cancellation_notice WHERE tenant_id = ?', [$tenant->getId()]);
    foreach ([$staff, $otherStaff] as $employee) {
        $entityManager->getRepository(App\Entity\TenantMembership::class)
            ->findForUserAndTenant($employee, $tenant)
            ->acknowledgeAppointmentCancellationsThrough($existingNoticeId);
    }
    $entityManager->flush();

    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($staff, 'api', ['ROLE_TENANT_STAFF']));
    $customerController = new App\Controller\ApiAppointmentController(
        $entityManager,
        new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']),
        $entityManager->getRepository(App\Entity\TenantMembership::class),
        $security,
        new App\Service\AppointmentBlocker($entityManager),
        new App\Service\AppointmentStaffNotifier($mailer, $emailLogger, 'test@example.invalid', 'https://admin.example.invalid', $entityManager->getRepository(App\Entity\TenantMembership::class)),
    );
    $colorUpdate = $customerController->updateResource($resource->getId(), appointmentRequest([
        'name' => $resource->getName(), 'color' => '#c05283', 'isActive' => true,
    ]));
    appointmentCheck($colorUpdate->getStatusCode() === 200 && $resource->getColor() === '#c05283', 'Staff must be able to set a resource color.');
    $date = (new DateTimeImmutable('+3 days', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
    $base = ['typeId' => $type->getId(), 'resourceId' => $resource->getId(), 'date' => $date, 'note' => 'Manuell'];

    $guest = $controller->create(appointmentRequest($base + ['time' => '10:00', 'customerId' => null, 'guestName' => 'Gast Name']));
    appointmentCheck($guest->getStatusCode() === 201, 'Guest appointment must be created.');
    $guestData = json_decode((string) $guest->getContent(), true, 512, JSON_THROW_ON_ERROR)['appointment'];
    appointmentCheck($guestData['customer'] === 'Gast Name' && $guestData['customerId'] === null, 'Guest appointment must not require an app user.');
    appointmentCheck($emailLogger->entries === [], 'Guest appointments must not trigger a customer email.');
    $scheduledPushes = new ReflectionProperty(App\Service\ChatPushService::class, 'scheduledAppointmentBookings');
    appointmentCheck($scheduledPushes->getValue($push) === [], 'Guest appointments must not schedule a customer push.');

    $assigned = $customerController->updateResource($resource->getId(), appointmentRequest([
        'name' => $resource->getName(), 'color' => '#c05283', 'isActive' => true, 'userId' => $staff->getId(),
    ]));
    appointmentCheck($assigned->getStatusCode() === 200 && $resource->getAssignedUser() === $staff, 'A tenant staff account must be assignable to a person.');
    $invalidAssignment = $customerController->updateResource($resource->getId(), appointmentRequest([
        'name' => $resource->getName(), 'color' => '#c05283', 'isActive' => true, 'userId' => $customer->getId(),
    ]));
    appointmentCheck($invalidAssignment->getStatusCode() === 422 && $resource->getAssignedUser() === $staff, 'A customer account must not be assignable as a person.');

    $collision = $controller->create(appointmentRequest($base + ['time' => '10:15', 'customerId' => $customer->getId(), 'guestName' => '']));
    appointmentCheck($collision->getStatusCode() === 409, 'Overlapping appointments for one person must be rejected.');

    $account = $controller->create(appointmentRequest($base + ['time' => '11:00', 'customerId' => $customer->getId(), 'guestName' => '']));
    appointmentCheck($account->getStatusCode() === 201, 'Linked customer appointment must be created.');
    $accountData = json_decode((string) $account->getContent(), true, 512, JSON_THROW_ON_ERROR)['appointment'];
    appointmentCheck($accountData['customerId'] === $customer->getId(), 'Account appointment must retain the customer link.');
    appointmentCheck($accountData['resourceColor'] === $resource->getColor(), 'Appointment must carry its resource color.');
    $staffMails = array_values(array_filter($emailLogger->entries, static fn (array $entry): bool =>
        $entry['message'] === 'email.send.requested' && ($entry['context']['messageType'] ?? null) === 'appointment_assigned_to_staff',
    ));
    appointmentCheck(count($staffMails) === 1, 'A new appointment assigned to a staff user must trigger a staff email.');
    $createdMails = array_values(array_filter($emailLogger->entries, static fn (array $entry): bool =>
        $entry['message'] === 'email.send.requested' && ($entry['context']['messageType'] ?? null) === 'appointment_created_by_staff',
    ));
    appointmentCheck(count($createdMails) === 1, 'A linked customer appointment must trigger exactly one booking email.');
    appointmentCheck($scheduledPushes->getValue($push) === [$accountData['id']], 'A linked customer appointment must schedule exactly one booking push.');

    $conversation = new App\Entity\ChatConversation($tenant, $customer, App\Controller\ApiChatController::CONSENT_VERSION);
    $entityManager->persist($conversation);
    $foreignConversation = new App\Entity\ChatConversation($tenant, $outsider, App\Controller\ApiChatController::CONSENT_VERSION);
    $entityManager->persist($foreignConversation);
    $entityManager->flush();
    appointmentCheck($controller->linkChat($accountData['id'], appointmentRequest(['conversationId' => $foreignConversation->id]))->getStatusCode() === 422, 'A chat belonging to another customer must not be linked.');
    $linked = $controller->linkChat($accountData['id'], appointmentRequest(['conversationId' => $conversation->id]));
    appointmentCheck($linked->getStatusCode() === 200 && $entityManager->getRepository(App\Entity\Appointment::class)->find($accountData['id'])->getChatConversation() === $conversation, 'A customer conversation must link to the appointment.');
    appointmentCheck($controller->linkChat($guestData['id'], appointmentRequest([]))->getStatusCode() === 422, 'Guest appointments must not open a chat.');
    $guestChats = json_decode((string) $controller->availableChats($guestData['id'])->getContent(), true, 512, JSON_THROW_ON_ERROR);
    appointmentCheck($guestChats['conversations'] === [], 'Guest appointments must not expose chats.');
    $customerChats = json_decode((string) $controller->availableChats($accountData['id'])->getContent(), true, 512, JSON_THROW_ON_ERROR);
    appointmentCheck(count($customerChats['conversations']) >= 1, 'The linked customer conversation must be available from the appointment.');

    $outside = $controller->create(appointmentRequest($base + ['time' => '12:00', 'customerId' => $outsider->getId(), 'guestName' => '']));
    appointmentCheck($outside->getStatusCode() === 422, 'Accounts without this tenant customer role must be rejected.');

    $cancelGuest = $controller->cancel($guestData['id'], appointmentRequest(['notifyCustomer' => true]));
    appointmentCheck($cancelGuest->getStatusCode() === 200, 'Guest cancellation must work without an email address.');

    $block = new App\Entity\AppointmentBlock($tenant, null, $date, $date, '12:00', '13:00', 'Test block');
    $entityManager->persist($block);
    $entityManager->flush();
    $blocked = $controller->create(appointmentRequest($base + ['time' => '12:00', 'customerId' => null, 'guestName' => 'Blocked Guest']));
    appointmentCheck($blocked->getStatusCode() === 409, 'Global blocked times must reject admin booking.');
    appointmentCheck($scheduledPushes->getValue($push) === [$accountData['id']], 'Rejected bookings must not schedule a push.');

    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($customer, 'api', ['ROLE_CUSTOMER']));
    appointmentCheck($customerController->cancel($accountData['id'])->getStatusCode() === 200, 'Customer cancellation must succeed.');
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($staff, 'api', ['ROLE_TENANT_STAFF']));
    $notices = json_decode((string) $controller->customerCancellations()->getContent(), true, 512, JSON_THROW_ON_ERROR)['cancellations'];
    appointmentCheck(count($notices) === 1 && $notices[0]['appointmentId'] === $accountData['id'], 'Only customer cancellations must create an admin alert.');
    appointmentCheck($controller->acknowledgeCustomerCancellations(appointmentRequest(['throughId' => $notices[0]['id']]))->getStatusCode() === 200, 'Admin must be able to dismiss the alert.');
    $remaining = json_decode((string) $controller->customerCancellations()->getContent(), true, 512, JSON_THROW_ON_ERROR)['cancellations'];
    appointmentCheck($remaining === [], 'Dismissed customer cancellation must not reappear.');
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($otherStaff, 'api', ['ROLE_TENANT_STAFF']));
    $otherStaffNotices = json_decode((string) $controller->customerCancellations()->getContent(), true, 512, JSON_THROW_ON_ERROR)['cancellations'];
    appointmentCheck(count($otherStaffNotices) === 1, 'Dismissing an alert for one staff member must not hide it from another.');

    $weekday = (int) (new DateTimeImmutable($date, new DateTimeZone('Europe/Vienna')))->format('N');
    $entityManager->persist(new App\Entity\AppointmentAvailability($resource, $type, $weekday, '14:00', '15:00'));
    $entityManager->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($customer, 'api', ['ROLE_CUSTOMER']));
    $slotsResponse = $customerController->slots(Symfony\Component\HttpFoundation\Request::create('/api/v1/appointments/slots', 'GET', [
        'typeId' => $type->getId(), 'date' => $date,
    ]));
    $slots = json_decode((string) $slotsResponse->getContent(), true, 512, JSON_THROW_ON_ERROR)['slots'];
    $customerBooking = $customerController->book(appointmentRequest(['typeId' => $type->getId(), 'startsAt' => $slots[0]['startsAt']]));
    appointmentCheck($customerBooking->getStatusCode() === 201, 'Customer booking must succeed for the assigned person.');
    $staffMails = array_values(array_filter($emailLogger->entries, static fn (array $entry): bool =>
        $entry['message'] === 'email.send.requested' && ($entry['context']['messageType'] ?? null) === 'appointment_assigned_to_staff',
    ));
    appointmentCheck(count($staffMails) === 2, 'Customer booking must also notify the assigned staff user.');
    $customerBookingId = json_decode((string) $customerBooking->getContent(), true, 512, JSON_THROW_ON_ERROR)['appointment']['id'];
    $conversation->status = 'closed';
    $entityManager->flush();
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($staff, 'api', ['ROLE_TENANT_STAFF']));
    $newChat = $controller->linkChat($customerBookingId, appointmentRequest([]));
    $newChatId = json_decode((string) $newChat->getContent(), true, 512, JSON_THROW_ON_ERROR)['conversationId'] ?? null;
    appointmentCheck($newChat->getStatusCode() === 200 && is_int($newChatId) && $newChatId !== $conversation->id, 'Staff may open a new chat after prior customer consent.');

    $freshCustomer = new App\Entity\User('fresh-'.$suffix, 'fresh-'.$suffix.'@example.invalid', 'Fresh Customer');
    $freshCustomer->setPassword('not-a-login');
    $entityManager->persist($freshCustomer);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $freshCustomer, ['ROLE_CUSTOMER']));
    $freshStart = new DateTimeImmutable($date.' 16:00', new DateTimeZone('Europe/Vienna'));
    $freshAppointment = new App\Entity\Appointment($tenant, $resource, $type, $freshCustomer, $freshStart, $freshStart->modify('+30 minutes'), null);
    $entityManager->persist($freshAppointment);
    $entityManager->flush();
    $pendingResponse = $controller->linkChat($freshAppointment->getId(), appointmentRequest([]));
    $pendingId = json_decode((string) $pendingResponse->getContent(), true, 512, JSON_THROW_ON_ERROR)['conversationId'] ?? null;
    $pending = $entityManager->getRepository(App\Entity\ChatConversation::class)->find($pendingId);
    appointmentCheck($pendingResponse->getStatusCode() === 200 && $pending instanceof App\Entity\ChatConversation && $pending->consentedAt === null && $pending->consentVersion === null, 'Staff must be able to open an unconsented chat without inventing customer consent.');

    $chatApi = new App\Controller\ApiChatController(
        $security,
        new App\Service\ActiveTenantProvider($entityManager, $_ENV['APP_TENANT_SLUG']),
        $entityManager->getRepository(App\Entity\TenantMembership::class),
        $entityManager,
        new App\Service\ChatCipher(new App\Service\PrivateDataKeyPath(dirname(__DIR__))),
    );
    $chatApi->send(new Symfony\Component\HttpFoundation\Request([], [
        'conversationId' => $pendingId,
        'text' => 'Bitte melden Sie sich wegen Ihres Termins.',
        'requestId' => '55555555-5555-4555-8555-555555555555',
    ]), true);
    $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($freshCustomer, 'api', ['ROLE_CUSTOMER']));
    $beforeConsent = json_decode((string) $chatApi->list(new Symfony\Component\HttpFoundation\Request(), false)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    appointmentCheck($beforeConsent['activeConversationId'] === $pendingId && $beforeConsent['consented'] === false, 'Customer must see the staff chat without a false consent state.');
    $pendingRead = json_decode((string) $chatApi->read($pendingId, new Symfony\Component\HttpFoundation\Request(), false)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    appointmentCheck(count($pendingRead['messages']) === 1 && $pendingRead['messages'][0]['role'] === 'staff', 'Customer must be able to read the staff message before consenting.');
    $replyData = ['conversationId' => $pendingId, 'text' => 'Danke, ich melde mich.', 'requestId' => '66666666-6666-4666-8666-666666666666'];
    try {
        $chatApi->send(new Symfony\Component\HttpFoundation\Request([], $replyData), false);
        throw new RuntimeException('Customer reply without consent was accepted.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        appointmentCheck($exception->getStatusCode() === 422, 'Customer reply without consent must be rejected.');
    }
    $chatApi->send(new Symfony\Component\HttpFoundation\Request([], $replyData + [
        'consent' => 'true', 'consentVersion' => App\Controller\ApiChatController::CONSENT_VERSION,
    ]), false);
    appointmentCheck($pending->consentedAt !== null && $pending->consentVersion === App\Controller\ApiChatController::CONSENT_VERSION, 'Customer consent must be recorded on the first customer reply.');
    $afterConsent = json_decode((string) $chatApi->list(new Symfony\Component\HttpFoundation\Request(), false)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    appointmentCheck($afterConsent['consented'] === true, 'The customer chat must reflect the recorded consent.');

    echo "Admin appointment integration checks passed.\n";
} finally {
    $entityManager->getConnection()->rollBack();
    $kernel->shutdown();
}
