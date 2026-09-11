<?php
declare(strict_types=1);
namespace App\EventSubscriber;
use App\Service\ChatPushService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
final class ChatPushSubscriber implements EventSubscriberInterface {
 public function __construct(private readonly ChatPushService $push){}
 public static function getSubscribedEvents():array{return [KernelEvents::TERMINATE=>'send'];}
 public function send():void{$this->push->flushScheduled();}
}
