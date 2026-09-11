<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{ChatMessage,User,Tenant,WebPushSubscription};
use App\Repository\TenantMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Minishlink\WebPush\{WebPush,Subscription};
use Psr\Log\LoggerInterface;
final class ChatPushService {
 private array $scheduled=[];
 public function __construct(private readonly EntityManagerInterface $em,private readonly ChatCipher $cipher,private readonly LoggerInterface $logger,private readonly TenantMembershipRepository $memberships,#[Autowire('%kernel.project_dir%')] private readonly string $projectDir){}
 public function config():?array{
  $path=$_ENV['WEB_PUSH_KEY_FILE']??$this->projectDir.'/var/private/web-push.json';
  if(!is_file($path))return null;
  $keys=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
  return ['subject'=>$_ENV['APP_CLIENT_URL']??'https://aesculapp.floatbox.at','publicKey'=>$keys['publicKey'],'privateKey'=>$keys['privateKey']];
 }
 public function subscribe(User $user,Tenant $tenant,array $data):void{
  $endpoint=$data['endpoint']??null;$keys=$data['keys']??null;
  if(!is_string($endpoint)||strlen($endpoint)>2048||!is_array($keys))throw new HttpException(422);
  $url=parse_url($endpoint);$host=strtolower($url['host']??'');
  $allowed=$host==='fcm.googleapis.com'||$host==='web.push.apple.com'||str_ends_with($host,'.push.services.mozilla.com')||str_ends_with($host,'.notify.windows.com');
  if(!$allowed||($url['scheme']??'')!=='https'||isset($url['user'])||isset($url['pass'])||isset($url['fragment'])||isset($url['port'])&&$url['port']!==443)throw new HttpException(422);
  foreach(['p256dh'=>65,'auth'=>16] as $key=>$length){
   $value=$keys[$key]??null;
   if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]+={0,2}$/D',$value)||strlen(base64_decode(strtr($value,'-_','+/'),true)?:'')!==$length)throw new HttpException(422);
  }
  $hash=hash('sha256',$endpoint);$repo=$this->em->getRepository(WebPushSubscription::class);
  $sub=$repo->findOneBy(['endpointHash'=>$hash]);
  if(!$sub&&$repo->count(['user'=>$user,'tenant'=>$tenant])>=5)throw new HttpException(422,'Maximal fünf Geräte.');
  $encrypted=$this->cipher->encrypt(json_encode(['endpoint'=>$endpoint,'keys'=>$keys],JSON_THROW_ON_ERROR),'push:'.$hash);
  if(!$sub){$sub=new WebPushSubscription($tenant,$user,$hash,$encrypted);$this->em->persist($sub);}
  else{$sub->user=$user;$sub->tenant=$tenant;$sub->encryptedSubscription=$encrypted;}
  $this->em->flush();
 }
 public function remove(User $user,Tenant $tenant,string $endpoint):void{
  $sub=$this->em->getRepository(WebPushSubscription::class)->findOneBy(['endpointHash'=>hash('sha256',$endpoint),'user'=>$user,'tenant'=>$tenant]);
  if($sub){$this->em->remove($sub);$this->em->flush();}
 }
 public function schedule(int $id):void{$this->scheduled[]=$id;}
 public function flushScheduled():void{foreach($this->scheduled as $id)$this->deliver($id);$this->scheduled=[];}
 public function deliver(int $id):void{
  try{
   $config=$this->config();if(!$config)return;
   $connection=$this->em->getConnection();
   if(!$connection->executeStatement('UPDATE chat_message SET push_pending = 0 WHERE id = ? AND push_pending = 1',[$id]))return;
   $message=$this->em->getRepository(ChatMessage::class)->find($id);
   if(!$message)return;
   $this->em->refresh($message);
   if($message->customerReadAt!==null)return;
   $membership=$this->memberships->findForUserAndTenant($message->conversation->customer,$message->conversation->tenant);
   if(!$membership?->isChatPushEnabled())return;
   $subs=$this->em->getRepository(WebPushSubscription::class)->findBy(['user'=>$message->conversation->customer,'tenant'=>$message->conversation->tenant]);
   $push=new WebPush(['VAPID'=>$config],['TTL'=>3600],new \GuzzleHttp\Client(['timeout'=>5,'connect_timeout'=>3,'allow_redirects'=>false]));
   $retry=false;
   foreach($subs as $sub){
    try{
     $data=json_decode($this->cipher->decrypt($sub->encryptedSubscription,'push:'.$sub->endpointHash),true,512,JSON_THROW_ON_ERROR);
     $report=$push->sendOneNotification(Subscription::create($data),json_encode(['title'=>'Neue Antwort','body'=>'Sie haben eine neue Antwort von Ihrer Apotheke.'],JSON_THROW_ON_ERROR));
     if($report->isSubscriptionExpired()){$this->em->remove($sub);}
     elseif(!$report->isSuccess()){$retry=true;$this->logger->warning('chat.push.delivery_failed',['messageId'=>$id]);}
    }catch(\Throwable $e){$retry=true;$this->logger->warning('chat.push.delivery_failed',['messageId'=>$id,'errorClass'=>$e::class]);}
   }
   $this->em->flush();
   if($retry)$connection->executeStatement('UPDATE chat_message SET push_pending = 1 WHERE id = ?',[$id]);
  }catch(\Throwable $e){
   try{$this->em->getConnection()->executeStatement('UPDATE chat_message SET push_pending = 1 WHERE id = ?',[$id]);}catch(\Throwable){}
   $this->logger->warning('chat.push.failed',['messageId'=>$id,'errorClass'=>$e::class]);
  }
 }
}
