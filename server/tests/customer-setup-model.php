<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;

$tenant = new Tenant('Testapotheke', 'setup-test');
$user = new User('kunde@example.test', 'kunde@example.test', 'Neuer Kunde');
$membership = new TenantMembership($tenant, $user);
if ($membership->isCustomerSetupCompleted()) throw new RuntimeException('New customers must begin without completed setup.');

$user->setNames('Erika', null);
if ($user->getFirstName() !== 'Erika' || $user->getLastName() !== null) {
    throw new RuntimeException('Partial profile details must be accepted.');
}
$user->setNames('Erika', 'Beispiel');
$membership->completeCustomerSetup();
if ($user->getFirstName() !== 'Erika' || $user->getLastName() !== 'Beispiel' || $user->getDisplayName() !== 'Erika Beispiel') {
    throw new RuntimeException('The display name must reflect the separate names.');
}
if (!$membership->isCustomerSetupCompleted()) throw new RuntimeException('Completed setup must be persisted on the membership.');

echo "Customer setup model checks passed.\n";
