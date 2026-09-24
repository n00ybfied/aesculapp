<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);

function frequencyCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function frequencyRequest(array $data): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/appointments',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($data, JSON_THROW_ON_ERROR),
    );
}

function frequencySlots(App\Controller\ApiAppointmentController $api, int $typeId, string $date): array
{
    return json_decode((string) $api->slots(Symfony\Component\HttpFoundation\Request::create(
        '/api/v1/appointments/slots', 'GET', ['typeId' => $typeId, 'date' => $date,
    ]))->getContent(), true, 512, JSON_THROW_ON_ERROR)['slots'];
}

$entityManager->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $tenant = new App\Entity\Tenant('Appointment Frequency Test', 'frequency-'.$suffix);
    $customer = new App\Entity\User('frequency-'.$suffix, 'frequency-'.$suffix.'@example.invalid', 'First Customer');
    $otherCustomer = new App\Entity\User('frequency-other-'.$suffix, 'frequency-other-'.$suffix.'@example.invalid', 'Other Customer');
    $customer->setPassword('not-a-login');
    $otherCustomer->setPassword('not-a-login');
    $type = new App\Entity\AppointmentType($tenant, 'Beratung A', null, 30, 5);
    $otherType = new App\Entity\AppointmentType($tenant, 'Beratung B', null, 30, 5);
    $resource = new App\Entity\AppointmentResource($tenant, 'Test Person');
    foreach ([$tenant, $customer, $otherCustomer, $type, $otherType, $resource] as $item) $entityManager->persist($item);
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $customer, ['ROLE_CUSTOMER']));
    $entityManager->persist(new App\Entity\TenantMembership($tenant, $otherCustomer, ['ROLE_CUSTOMER']));

    $firstDay = new DateTimeImmutable('+3 days');
    $nextDay = $firstDay->modify('+1 day');
    foreach ([$firstDay, $nextDay] as $day) {
        $entityManager->persist(new App\Entity\AppointmentAvailability($resource, $type, (int) $day->format('N'), '10:00', '11:00'));
    }
    $entityManager->persist(new App\Entity\AppointmentAvailability($resource, $otherType, (int) $nextDay->format('N'), '11:00', '12:00'));
    $entityManager->flush();

    $api = new App\Controller\ApiAppointmentController(
        $entityManager,
        new App\Service\ActiveTenantProvider($entityManager, $tenant->getSlug()),
        $entityManager->getRepository(App\Entity\TenantMembership::class),
        $security,
        new App\Service\AppointmentBlocker($entityManager),
    );
    $asCustomer = static function (App\Entity\User $user) use ($storage): void {
        $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'api', ['ROLE_CUSTOMER']));
    };

    $asCustomer($customer);
    $firstStart = frequencySlots($api, $type->getId(), $firstDay->format('Y-m-d'))[0]['startsAt'];
    $first = $api->book(frequencyRequest(['typeId' => $type->getId(), 'startsAt' => $firstStart]));
    frequencyCheck($first->getStatusCode() === 201, 'First appointment must succeed.');
    $firstId = json_decode((string) $first->getContent(), true, 512, JSON_THROW_ON_ERROR)['appointment']['id'];
    frequencyCheck(frequencySlots($api, $type->getId(), $nextDay->format('Y-m-d')) === [], 'Nearby slots of the same type must be hidden from this customer.');

    $asCustomer($otherCustomer);
    $nearbyStart = frequencySlots($api, $type->getId(), $nextDay->format('Y-m-d'))[0]['startsAt'];
    $asCustomer($customer);
    $blocked = $api->book(frequencyRequest(['typeId' => $type->getId(), 'startsAt' => $nearbyStart]));
    $blockedData = json_decode((string) $blocked->getContent(), true, 512, JSON_THROW_ON_ERROR);
    frequencyCheck($blocked->getStatusCode() === 409 && $blockedData['code'] === 'appointment_type_week_limit', 'Direct booking must enforce seven days for the same customer and type.');

    $differentStart = frequencySlots($api, $otherType->getId(), $nextDay->format('Y-m-d'))[0]['startsAt'];
    frequencyCheck($api->book(frequencyRequest(['typeId' => $otherType->getId(), 'startsAt' => $differentStart]))->getStatusCode() === 201, 'A different appointment type must remain bookable.');
    frequencyCheck($api->cancel($firstId)->getStatusCode() === 200, 'First appointment must be cancellable.');
    frequencyCheck($api->book(frequencyRequest(['typeId' => $type->getId(), 'startsAt' => $nearbyStart]))->getStatusCode() === 201, 'A cancelled appointment must not block a later booking.');

    $boundaryDay = $nextDay->modify('+7 days');
    $boundarySlots = frequencySlots($api, $type->getId(), $boundaryDay->format('Y-m-d'));
    frequencyCheck(count($boundarySlots) === 1, 'A slot exactly seven days later must be available.');
    frequencyCheck($api->book(frequencyRequest(['typeId' => $type->getId(), 'startsAt' => $boundarySlots[0]['startsAt']]))->getStatusCode() === 201, 'Booking exactly seven days later must succeed.');

    echo "Customer appointment frequency checks passed.\n";
} finally {
    while ($entityManager->getConnection()->isTransactionActive()) $entityManager->getConnection()->rollBack();
    $kernel->shutdown();
}
