<?php
declare(strict_types=1);
// Integration regression using a rollback-only outer transaction: no receipts, accounts or points remain.
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$storage = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage',$storage);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$provider = new App\Service\ActiveTenantProvider($em,$_ENV['APP_TENANT_SLUG']);
$api = new App\Controller\ApiLoyaltyReceiptController($provider,$em,new App\Service\LoyaltyReceiptQrParser(),new App\Service\PointAccountProvisioner($em),$security,new Psr\Log\NullLogger());
function receiptCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function receiptRequest(string $raw): Symfony\Component\HttpFoundation\Request { return new Symfony\Component\HttpFoundation\Request(content: json_encode(['rawQrValue'=>$raw], JSON_THROW_ON_ERROR)); }
$em->beginTransaction();
try {
 $tenant=$provider->get();
 $tenant->setReceiptQrPrefix('_HA_0_');$tenant->setPointsPerEuro(10);$tenant->setAllowDuplicateReceiptImports(false);
 $email='receipt-test-'.bin2hex(random_bytes(6)).'@example.invalid';
 $user=new App\Entity\User($email,$email,'Receipt Test');$user->setPassword('not-a-login');
 $em->persist($user);$em->persist(new App\Entity\TenantMembership($tenant,$user,['ROLE_CUSTOMER']));$em->flush();
 $storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user,'api',['ROLE_CUSTOMER']));
 $raw='_HA_0_K26/TEST001_2026-09-12T09:00:00_0,00_0,00_0,00_0,00_26,50_0_0_0_signature';
 $response=$api->import(receiptRequest($raw));
 receiptCheck($response->getStatusCode()===200,'Receipt import must succeed');
 receiptCheck(json_decode($response->getContent(),true)['addedPoints']===265,'Points must use the eligible receipt amount');
 receiptCheck((int)$em->getConnection()->fetchOne("SELECT COUNT(*) FROM point_transaction pt JOIN point_account account ON account.id = pt.account_id WHERE pt.type = 'receipt_credit' AND account.owner_id = ?",[$user->getId()])===1,'Receipt must create exactly one point transaction');
 receiptCheck((int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM loyalty_receipt_redemption receipt JOIN point_account account ON account.id = receipt.account_id WHERE account.owner_id = ?',[$user->getId()])===1,'Receipt must store a redemption record');
 receiptCheck($api->import(receiptRequest($raw))->getStatusCode()===409,'Duplicate receipt must be rejected');
 receiptCheck($api->import(receiptRequest(str_replace('_HA_0_','_OTHER_', $raw)))->getStatusCode()===422,'Unexpected QR prefix must be rejected');
 $tenant->setAllowDuplicateReceiptImports(true);
 receiptCheck($api->import(receiptRequest($raw))->getStatusCode()===200,'Debug duplicate mode must permit a repeat import');
 receiptCheck((int)$em->getConnection()->fetchOne("SELECT COUNT(*) FROM point_transaction pt JOIN point_account account ON account.id = pt.account_id WHERE pt.type = 'receipt_credit' AND account.owner_id = ?",[$user->getId()])===2,'Debug duplicate must still create a ledger transaction');
 echo "Receipt checks passed: prefix, eligible amount, ledger transaction, duplicate protection and debug duplicate mode.\n";
} finally {
 while($em->getConnection()->isTransactionActive())$em->getConnection()->rollBack();
 $kernel->shutdown();
}
