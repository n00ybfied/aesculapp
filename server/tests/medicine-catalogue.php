<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$tenant = (new App\Service\ActiveTenantProvider($kernel->getContainer()->get('doctrine')->getManager(), $_ENV['APP_TENANT_SLUG']))->get();
$tenantId = (int) $tenant->getId();
$catalogue = new App\Service\MedicineCatalogue($db);

function medicineCheck(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$db->beginTransaction();
try {
    foreach ([['9999997', 'TEST ROT', '0'], ['9999998', 'TEST GELB', ''], ['9999999', 'TEST GRUEN', '']] as [$pzn, $name, $delivery]) {
        $db->executeStatement('INSERT INTO medicine_catalogue (pzn, name, name_search, pack, delivery_code, edition) VALUES (?, ?, ?, ?, ?, ?)', [$pzn, $name, mb_strtolower($name), '10 ST', $delivery, '1126']);
    }
    $db->executeStatement('INSERT INTO medicine_stock (tenant_id, pzn, stock_qty, reserved_qty) VALUES (?, ?, ?, ?)', [$tenantId, '9999999', 4, 1]);
    $db->executeStatement('INSERT INTO medicine_import (source_key, sha256, imported_at, source_mtime, row_count) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?) ON DUPLICATE KEY UPDATE source_mtime = UTC_TIMESTAMP()', ['stock:'.$tenantId, str_repeat('a', 64), 1]);
    medicineCheck($catalogue->find('9999997', $tenantId)['status'] === 'unavailable', 'Zero delivery code should show unavailable.');
    medicineCheck($catalogue->find('9999998', $tenantId)['status'] === 'orderable', 'Blank delivery code should show orderable under provisional rule.');
    medicineCheck($catalogue->find('9999999', $tenantId)['status'] === 'available', 'Positive free stock should take precedence.');
    $public = $catalogue->find('9999999', $tenantId);
    medicineCheck(array_keys($public) === ['pzn', 'name', 'pack', 'status'], 'Customer response must not contain stock or prices.');
    medicineCheck(count($catalogue->search('TEST GRUEN', $tenantId)) === 1, 'Name autocomplete should find the article.');
    $db->executeStatement('UPDATE medicine_import SET source_mtime = ? WHERE source_key = ?', [gmdate('Y-m-d H:i:s', time() - 3600), 'stock:'.$tenantId]);
    medicineCheck($catalogue->find('9999999', $tenantId)['status'] === 'unknown', 'Stale stock must not show green.');
    echo "MEDICINE_CATALOGUE_OK\n";
} finally {
    $db->rollBack();
}
