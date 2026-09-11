<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
// No network requests: validates VAPID signing and payload encryption against a mock transport.
$server=Minishlink\WebPush\VAPID::createVapidKeys();
$browser=Minishlink\WebPush\VAPID::createVapidKeys();
$mock=new GuzzleHttp\Handler\MockHandler([new GuzzleHttp\Psr7\Response(201)]);
$client=new GuzzleHttp\Client(['handler'=>GuzzleHttp\HandlerStack::create($mock)]);
$push=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>'https://example.org',...$server]],[], $client);
$subscription=Minishlink\WebPush\Subscription::create(['endpoint'=>'https://fcm.googleapis.com/fcm/send/test','keys'=>['p256dh'=>$browser['publicKey'],'auth'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')]]);
$report=$push->sendOneNotification($subscription,'{"title":"Neue Antwort"}');
if(!$report->isSuccess())throw new RuntimeException('Mock push send failed.');
if(str_contains((string)$report->getRequest()->getBody(),'Neue Antwort'))throw new RuntimeException('Push payload is not encrypted.');
echo "Web-Push signing/encryption with mock transport passed. No real push sent.\n";
