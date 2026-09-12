<?php
declare(strict_types=1);
// Integration regression using a rollback-only outer transaction: no test accounts/messages remain.
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
$cipher = new App\Service\ChatCipher(dirname(__DIR__));
$api = new App\Controller\ApiChatController($security,$provider,$em->getRepository(App\Entity\TenantMembership::class),$em,$cipher);
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function status(callable $call,int $expected): void { try { $response=$call(); check($response->getStatusCode()===$expected,'Unexpected status'); } catch(Symfony\Component\HttpKernel\Exception\HttpException $e) { check($e->getStatusCode()===$expected,'Unexpected exception status '.$e->getStatusCode()); } }
function asUser($storage,$user):void{$storage->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user,'api',$user->getRoles()));}
$em->beginTransaction();
try {
 $users=[];
 foreach(['customer','other','staff'] as $role){
  $name='chat-test-'.bin2hex(random_bytes(6)).'@example.invalid';
  $user=new App\Entity\User($name,$name,'Chat Test');$user->setPassword('not-a-login');
  $em->persist($user);$em->persist(new App\Entity\TenantMembership($provider->get(),$user,[$role==='staff'?'ROLE_TENANT_STAFF':'ROLE_CUSTOMER']));$users[$role]=$user;
 }
 $em->flush();asUser($storage,$users['staff']);$initialAdminUnread=json_decode($api->openCount()->getContent(),true)['count'];asUser($storage,$users['customer']);
 $params=['text'=>'Private regression message','requestId'=>'11111111-1111-4111-8111-111111111111'];
 status(fn()=>$api->send(new Symfony\Component\HttpFoundation\Request([], $params),false),422);
 $params+=['consent'=>'true','consentVersion'=>App\Controller\ApiChatController::CONSENT_VERSION];
 $result=json_decode($api->send(new Symfony\Component\HttpFoundation\Request([],$params),false)->getContent(),true);
 $id=$result['conversationId'];
 $read=$api->read($id,new Symfony\Component\HttpFoundation\Request(),false);
 check($read->headers->get('Cache-Control')==='no-store, private','No-store missing');
 $data=json_decode($read->getContent(),true);
 check(count($data['messages'])===1 && $data['messages'][0]['text']===$params['text'],'Message roundtrip failed');
 check($data['conversation']['subject']==='Private regression message …','Automatic subject failed');
 $listed=json_decode($api->list(new Symfony\Component\HttpFoundation\Request(['page'=>2]),false)->getContent(),true);
 check($listed['activeConversationId']===$id,'Active conversation must be independent of pagination');
 $duplicate=$params;$duplicate['requestId']='33333333-3333-4333-8333-333333333333';
 status(fn()=>$api->send(new Symfony\Component\HttpFoundation\Request([],$duplicate),false),409);
 $subject=$em->getConnection()->fetchOne('SELECT encrypted_subject FROM chat_conversation WHERE id = ?',[$id]);
 check(str_starts_with($subject,'v1:')&&!str_contains($subject,'Private regression'),'Subject not encrypted');
 $stored=$em->getConnection()->fetchOne('SELECT encrypted_text FROM chat_message WHERE conversation_id = ?',[$id]);
 check(!str_contains($stored,$params['text']) && str_starts_with($stored,'v1:'),'Plaintext storage');
 $api->send(new Symfony\Component\HttpFoundation\Request([],$params),false);
 check((int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM chat_message WHERE conversation_id = ?',[$id])===1,'Duplicate message');
 asUser($storage,$users['other']);status(fn()=>$api->read($id,new Symfony\Component\HttpFoundation\Request(),false),404);
 asUser($storage,$users['customer']);status(fn()=>$api->list(new Symfony\Component\HttpFoundation\Request(),true),403);
 asUser($storage,$users['staff']);
 $rawUnread=(int)$em->getConnection()->fetchOne("SELECT COUNT(*) FROM chat_message message JOIN chat_conversation conversation ON conversation.id = message.conversation_id WHERE conversation.id = ? AND message.sender_role = 'customer' AND message.staff_read_at IS NULL",[$id]);
 $badgeUnread=json_decode($api->openCount()->getContent(),true)['count'];
 check($rawUnread===1 && $badgeUnread===$initialAdminUnread+1,'Unread customer message not counted for staff (raw '.$rawUnread.', badge '.$badgeUnread.')');
 $staffRead=json_decode($api->read($id,new Symfony\Component\HttpFoundation\Request(),true)->getContent(),true);
 check(json_decode($api->openCount()->getContent(),true)['count']===$initialAdminUnread+1,'Admin GET must not mark messages read');
 $lastCustomer=end($staffRead['messages'])['id'];
 $api->markStaffRead($id,new Symfony\Component\HttpFoundation\Request(content:json_encode(['lastMessageId'=>$lastCustomer])));
 check(json_decode($api->openCount()->getContent(),true)['count']===$initialAdminUnread,'Staff read acknowledgement failed');
 $reply=['text'=>'Test reply','conversationId'=>$id,'requestId'=>'44444444-4444-4444-8444-444444444444'];
 $api->send(new Symfony\Component\HttpFoundation\Request([],$reply),true);
 asUser($storage,$users['customer']);
 check(json_decode($api->unreadCount()->getContent(),true)['count']===1,'Unread staff reply not counted');
 $readReply=json_decode($api->read($id,new Symfony\Component\HttpFoundation\Request(),false)->getContent(),true);
 check(json_decode($api->unreadCount()->getContent(),true)['count']===1,'GET must not mark messages read');
 $lastReply=end($readReply['messages'])['id'];
 $api->markRead($id,new Symfony\Component\HttpFoundation\Request(content:json_encode(['lastMessageId'=>$lastReply])));
 check(json_decode($api->unreadCount()->getContent(),true)['count']===0,'Read acknowledgement failed');
 asUser($storage,$users['staff']);
 $api->close($id);
 asUser($storage,$users['customer']);
 $params['conversationId']=$id;$params['requestId']='22222222-2222-4222-8222-222222222222';
 status(fn()=>$api->send(new Symfony\Component\HttpFoundation\Request([],$params),false),409);
 check($cipher->decrypt($cipher->encrypt('secret','context'),'context')==='secret','Cipher roundtrip');
 try {$cipher->decrypt($cipher->encrypt('secret','context'),'other');throw new LogicException('Context accepted');}catch(RuntimeException $expected){}
 echo "Chat checks passed: consent, encryption, roundtrip, idempotency, owner isolation, customer/staff unread badges, close locking, cache headers.\n";
} finally {
 while($em->getConnection()->isTransactionActive())$em->getConnection()->rollBack();
 $kernel->shutdown();
}
