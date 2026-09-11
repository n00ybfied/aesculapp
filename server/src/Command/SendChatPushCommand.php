<?php
declare(strict_types=1);
namespace App\Command;
use App\Service\ChatPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:push:send')]
final class SendChatPushCommand extends Command{
 public function __construct(private readonly ChatPushService $push,private readonly EntityManagerInterface $em){parent::__construct();}
 protected function execute(InputInterface $input,OutputInterface $output):int{
  if(!$this->push->config()){$output->writeln('Web-Push key is not configured.');return 1;}
  $ids=$this->em->getConnection()->fetchFirstColumn('SELECT id FROM chat_message WHERE push_pending = 1 ORDER BY id LIMIT 20');
  foreach($ids as $id)$this->push->deliver((int)$id);
  $output->writeln('Pending chat notifications processed.');return 0;
 }
}
