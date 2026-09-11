<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{ChatConversation, ChatMessage, User};
use App\Repository\TenantMembershipRepository;
use App\Service\{ActiveTenantProvider, ChatCipher};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, JsonResponse, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApiChatController {
    public const CONSENT_VERSION = 'chat-2026-09-11-v1';
    public const NOTICE = 'Nachrichten und Bilder werden verschlüsselt übertragen und auf dem Server verschlüsselt gespeichert. Es handelt sich nicht um Ende-zu-Ende-Verschlüsselung. Berechtigte Mitarbeiter Ihrer Apotheke können Ihre Nachrichten und Anhänge lesen.';
    public const CONSENT = 'Ich habe die Datenschutzhinweise gelesen und willige in die Verarbeitung meiner Nachrichten und Bilder einschließlich gegebenenfalls enthaltener Gesundheitsdaten zur Bearbeitung meiner Anfrage ein.';
    public function __construct(private readonly Security $security, private readonly ActiveTenantProvider $tenant, private readonly TenantMembershipRepository $memberships, private readonly EntityManagerInterface $em, private readonly ChatCipher $cipher,private readonly ?\App\Service\ChatPushService $push=null) {}
    #[Route('/api/v1/chat/push', methods:['GET','POST','DELETE'])]
    public function push(Request $request):JsonResponse{
        $user=$this->user(false);
        if($request->isMethod('GET'))return $this->json(['publicKey'=>$this->push?->config()['publicKey']??null]);
        if(!$this->push)throw new HttpException(503);
        $data=$request->toArray();
        if($request->isMethod('DELETE')){$this->push->remove($user,$this->tenant->get(),(string)($data['endpoint']??''));}
        else{if(!$this->push->config())throw new HttpException(503);$this->push->subscribe($user,$this->tenant->get(),$data);}
        return $this->json(['success'=>true]);
    }
    #[Route('/api/v1/chat/push/status',methods:['POST'])]
    public function pushStatus(Request $request):JsonResponse{
        $user=$this->user(false);$data=$request->toArray();$endpoint=$data['endpoint']??'';
        if(!is_string($endpoint))throw new HttpException(422);
        $subscription=$this->em->getRepository(\App\Entity\WebPushSubscription::class)->findOneBy(['user'=>$user,'tenant'=>$this->tenant->get(),'endpointHash'=>hash('sha256',$endpoint)]);
        return $this->json(['subscribed'=>$subscription!==null]);
    }
    private function user(bool $admin): User {
        $user = $this->security->getUser();
        if (!$user instanceof User) { throw new HttpException(401); }
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        if (!$membership || ($admin && !array_intersect(['ROLE_TENANT_ADMIN','ROLE_TENANT_STAFF'], $membership->getRoles())) || (!$admin && !in_array('ROLE_CUSTOMER', $membership->getRoles(), true))) { throw new HttpException(403); }
        return $user;
    }
    private function json(array $data, int $status = 200): JsonResponse {
        return new JsonResponse($data, $status, ['Cache-Control'=>'private, no-store', 'Pragma'=>'no-cache']);
    }
    private function conversation(int $id, User $user, bool $admin): ChatConversation {
        $chat = $this->em->getRepository(ChatConversation::class)->find($id);
        if (!$chat || $chat->tenant->getId() !== $this->tenant->get()->getId() || (!$admin && $chat->customer->getId() !== $user->getId())) { throw new HttpException(404); }
        return $chat;
    }
    private function summary(ChatConversation $chat): array {
        $subject = $chat->encryptedSubject === null ? null : $this->cipher->decrypt($chat->encryptedSubject,$this->context($chat).':subject');
        if ($subject === null) {
            $first = $this->em->createQueryBuilder()->select('m.encryptedText')->from(ChatMessage::class,'m')->where('m.conversation = :chat')->setParameter('chat',$chat)->orderBy('m.id','ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
            $subject = $this->fallbackSubject($first ? $this->cipher->decrypt($first['encryptedText'],$this->context($chat).':text') : '');
        }
        $unread = $this->em->getRepository(ChatMessage::class)->count(['conversation'=>$chat,'senderRole'=>'staff','customerReadAt'=>null]);
        return ['id'=>$chat->id,'unreadCount'=>$unread,'subject'=>$subject,'status'=>$chat->status,'customerName'=>$chat->customer->getDisplayName(),'createdAt'=>$chat->createdAt->format(DATE_ATOM),'updatedAt'=>$chat->updatedAt->format(DATE_ATOM)];
    }
    #[Route('/api/v1/chat/unread-count', methods:['GET'])]
    public function unreadCount(): JsonResponse {
        $user=$this->user(false);
        $count=$this->em->createQueryBuilder()->select('COUNT(m.id)')->from(ChatMessage::class,'m')->join('m.conversation','c')->where('c.tenant = :tenant AND c.customer = :customer AND m.senderRole = :role AND m.customerReadAt IS NULL')->setParameter('tenant',$this->tenant->get())->setParameter('customer',$user)->setParameter('role','staff')->getQuery()->getSingleScalarResult();
        return $this->json(['count'=>(int)$count]);
    }
    #[Route('/api/v1/chat/{id}/read', methods:['POST'], requirements:['id'=>'\d+'])]
    public function markRead(int $id, Request $request): JsonResponse {
        $chat=$this->conversation($id,$this->user(false),false);
        $lastId=$request->toArray()['lastMessageId'] ?? null;
        if (!is_int($lastId) || $lastId<1) { return $this->json(['message'=>'Ungültige Nachricht.'],422); }
        $last=$this->em->getRepository(ChatMessage::class)->findOneBy(['id'=>$lastId,'conversation'=>$chat]);
        if (!$last) { throw new HttpException(404); }
        $this->em->createQueryBuilder()->update(ChatMessage::class,'m')->set('m.customerReadAt',':now')->where('m.conversation = :chat AND m.id <= :last AND m.senderRole = :role AND m.customerReadAt IS NULL')->setParameter('now',new \DateTimeImmutable())->setParameter('chat',$chat)->setParameter('last',$lastId)->setParameter('role','staff')->getQuery()->execute();
        return $this->json(['success'=>true]);
    }
    private function fallbackSubject(string $text):string {
        $text = trim(preg_replace('/\s+/u',' ',$text) ?? '');
        if ($text === '') { return 'Bildanfrage …'; }
        $sentence = preg_split('/(?<=[.!?])\s+/u',$text,2)[0];
        return rtrim(mb_substr($sentence,0,100)).' …';
    }
    #[Route('/api/v1/admin/chat/open-count', methods:['GET'])]
    public function openCount(): JsonResponse {
        $this->user(true);
        return $this->json(['count'=>$this->em->getRepository(ChatConversation::class)->count(['tenant'=>$this->tenant->get(),'status'=>'open'])]);
    }
    #[Route('/api/v1/chat', methods:['GET'], defaults: ['admin'=>false])]
    #[Route('/api/v1/admin/chat', methods:['GET'], defaults: ['admin'=>true])]
    public function list(Request $request, bool $admin): JsonResponse {
        $user = $this->user($admin);
        $page = max(1, $request->query->getInt('page', 1));
        $criteria = ['tenant'=>$this->tenant->get()];
        if (!$admin) { $criteria['customer'] = $user; }
        $repo = $this->em->getRepository(ChatConversation::class);
        $chats = $repo->findBy($criteria, ['updatedAt'=>'DESC','id'=>'DESC'], 20, ($page-1)*20);
        $consent = $this->em->getRepository(ChatConversation::class)->findOneBy(['tenant'=>$this->tenant->get(),'customer'=>$user,'consentVersion'=>self::CONSENT_VERSION]);
        $active = $admin ? null : $repo->findOneBy(['tenant'=>$this->tenant->get(),'customer'=>$user,'status'=>'open']);
        return $this->json(['activeConversationId'=>$active?->id,'conversations'=>array_map($this->summary(...),$chats),'total'=>$repo->count($criteria),'page'=>$page,'consentVersion'=>self::CONSENT_VERSION,'consentText'=>self::CONSENT,'notice'=>self::NOTICE,'consented'=>$consent !== null]);
    }
    #[Route('/api/v1/chat/{id}', methods:['GET'], requirements:['id'=>'\d+'], defaults:['admin'=>false])]
    #[Route('/api/v1/admin/chat/{id}', methods:['GET'], requirements:['id'=>'\d+'], defaults:['admin'=>true])]
    public function read(int $id, Request $request, bool $admin): JsonResponse {
        $chat = $this->conversation($id, $this->user($admin), $admin);
        $query = $this->em->createQueryBuilder()->select('m.id, m.senderRole, m.encryptedText, m.createdAt, CASE WHEN m.encryptedImage IS NULL THEN 0 ELSE 1 END AS hasImage')->from(ChatMessage::class,'m')->where('m.conversation = :chat')->setParameter('chat',$chat);
        $before = $request->query->getInt('before');
        if ($before > 0) { $query->andWhere('m.id < :before')->setParameter('before',$before); }
        $messages = $query->orderBy('m.id','DESC')->setMaxResults(51)->getQuery()->getResult();
        $more = count($messages)>50;
        $messages = array_reverse(array_slice($messages,0,50));
        return $this->json(['conversation'=>$this->summary($chat),'hasOlder'=>$more,'messages'=>array_map(fn(array $m)=>[
            'id'=>$m['id'],'role'=>$m['senderRole'],'text'=>$this->cipher->decrypt($m['encryptedText'],$this->context($chat).':text'),
            'hasImage'=>(bool)$m['hasImage'],'createdAt'=>$m['createdAt']->format(DATE_ATOM)
        ],$messages)]);
    }
    private function context(ChatConversation $chat): string { return 'chat:'.$chat->tenant->getId().':'.$chat->id; }

    #[Route('/api/v1/chat/send', methods:['POST'], defaults:['admin'=>false])]
    #[Route('/api/v1/admin/chat/send', methods:['POST'], defaults:['admin'=>true])]
    public function send(Request $request, bool $admin): JsonResponse {
        $user = $this->user($admin);
        $text = trim($request->request->getString('text'));
        $subject = trim($request->request->getString('subject'));
        if (mb_strlen($subject)>160) { return $this->json(['message'=>'Der Betreff darf maximal 160 Zeichen enthalten.'],422); }
        $requestId = $request->request->getString('requestId');
        if (mb_strlen($text)>5000 || !preg_match('/^[a-f0-9-]{36}$/D',$requestId)) { return $this->json(['message'=>'Nachricht ist ungültig (maximal 5.000 Zeichen).'],422); }
        $file = $request->files->get('image');
        if ($file !== null && !$file instanceof UploadedFile) { return $this->json(['message'=>'Ungültiges Bild.'],422); }
        $image = $file instanceof UploadedFile ? $this->image($file) : null;
        if ($text === '' && $image === null) { return $this->json(['message'=>'Bitte eine Nachricht oder ein Bild hinzufügen.'],422); }
        $chatId = $request->request->getInt('conversationId');
        $this->em->beginTransaction();
        try {
            // Serializes creation as well as sending from multiple customer devices.
            $membership = $this->memberships->findForUserAndTenant($user,$this->tenant->get());
            $this->em->lock($membership, LockMode::PESSIMISTIC_WRITE);
            $retry = $this->em->createQueryBuilder()->select('m')->from(ChatMessage::class,'m')->join('m.conversation','c')->where('m.requestId = :request AND m.sender = :sender AND c.tenant = :tenant')->setParameter('request',$requestId)->setParameter('sender',$user)->setParameter('tenant',$this->tenant->get())->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($retry) { $this->em->commit(); return $this->json(['conversationId'=>$retry->conversation->id],200); }
            if ($chatId > 0) {
                $chat = $this->conversation($chatId,$user,$admin);
            } else {
                if ($admin) { throw new HttpException(422); }
                $chat = $this->em->getRepository(ChatConversation::class)->findOneBy(['tenant'=>$this->tenant->get(),'customer'=>$user,'status'=>'open']);
                if ($chat) { throw new HttpException(409,'Es gibt bereits ein offenes Gespräch.'); }
                if (!$chat) {
                    $previous = $this->em->getRepository(ChatConversation::class)->findOneBy(['tenant'=>$this->tenant->get(),'customer'=>$user,'consentVersion'=>self::CONSENT_VERSION]);
                    if (!$previous && ($request->request->getString('consentVersion') !== self::CONSENT_VERSION || $request->request->getString('consent') !== 'true')) { throw new HttpException(422,'Bitte stimmen Sie zuerst den Datenschutzhinweisen zu.'); }
                    $chat = new ChatConversation($this->tenant->get(),$user,self::CONSENT_VERSION);
                    if ($previous) { $chat->consentedAt = $previous->consentedAt; }
                    $this->em->persist($chat);
                    $this->em->flush();
                    $chat->encryptedSubject = $this->cipher->encrypt($subject !== '' ? $subject : $this->fallbackSubject($text),$this->context($chat).':subject');
                    $this->em->flush();
                }
            }
            $this->em->refresh($chat,LockMode::PESSIMISTIC_WRITE);
            $existing = $this->em->getRepository(ChatMessage::class)->findOneBy(['conversation'=>$chat,'requestId'=>$requestId]);
            if (!$existing) {
                if ($chat->status !== 'open') { throw new HttpException(409,'Dieses Gespräch wurde abgeschlossen.'); }
                $message = new ChatMessage($chat,$user,$admin?'staff':'customer',$requestId);
                $message->pushPending=$admin;
                $message->encryptedText = $this->cipher->encrypt($text,$this->context($chat).':text');
                $message->encryptedImage = $image === null ? null : $this->cipher->encrypt($image,$this->context($chat).':image');
                $chat->updatedAt = new \DateTimeImmutable();
                $this->em->persist($message); $this->em->flush();
            }
            $this->em->commit();
            if($admin && isset($message))$this->push?->schedule($message->id);
            return $this->json(['conversationId'=>$chat->id],201);
        } catch (\Throwable $e) { $this->em->rollback(); throw $e; }
    }
    private function image(UploadedFile $file): string {
        if (!$file->isValid() || $file->getSize()>5*1024*1024 || !in_array($file->getMimeType(),['image/jpeg','image/png','image/webp'],true)) { throw new HttpException(422,'Erlaubt sind JPEG, PNG und WebP bis 5 MB.'); }
        $size = @getimagesize($file->getPathname());
        if (!$size || $size[0]*$size[1]>16000000) { throw new HttpException(422,'Das Bild darf maximal 16 Megapixel haben.'); }
        $source = @imagecreatefromstring(file_get_contents($file->getPathname()));
        if (!$source) { throw new HttpException(422,'Das Bild kann nicht gelesen werden.'); }
        $scale = min(1,2000/max($size[0],$size[1]));
        $width = max(1,(int)round($size[0]*$scale)); $height = max(1,(int)round($size[1]*$scale));
        $canvas = imagecreatetruecolor($width,$height);
        imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        imagecopyresampled($canvas,$source,0,0,0,0,$width,$height,$size[0],$size[1]);
        ob_start(); imagejpeg($canvas,null,90); $bytes = ob_get_clean();
        imagedestroy($source); imagedestroy($canvas);
        if (!is_string($bytes)) { throw new \RuntimeException('Image processing failed.'); }
        return $bytes;
    }
    #[Route('/api/v1/chat/{id}/images/{messageId}', methods:['GET'], defaults:['admin'=>false])]
    #[Route('/api/v1/admin/chat/{id}/images/{messageId}', methods:['GET'], defaults:['admin'=>true])]
    public function attachment(int $id, int $messageId, bool $admin): Response {
        $chat = $this->conversation($id,$this->user($admin),$admin);
        $message = $this->em->getRepository(ChatMessage::class)->findOneBy(['id'=>$messageId,'conversation'=>$chat]);
        if (!$message || $message->encryptedImage === null) { throw new HttpException(404); }
        return new Response($this->cipher->decrypt($message->encryptedImage,$this->context($chat).':image'),200,[
            'Content-Type'=>'image/jpeg','Cache-Control'=>'private, no-store','Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff','Content-Disposition'=>'inline; filename="chat-image.jpg"'
        ]);
    }
    #[Route('/api/v1/admin/chat/{id}/close', methods:['POST'])]
    public function close(int $id): JsonResponse {
        $chat = $this->conversation($id,$this->user(true),true);
        $this->em->wrapInTransaction(function() use($chat) {
            $this->em->refresh($chat,LockMode::PESSIMISTIC_WRITE);
            $chat->status='closed'; $chat->closedAt=$chat->updatedAt=new \DateTimeImmutable();
        });
        return $this->json(['conversation'=>$this->summary($chat)]);
    }
}
