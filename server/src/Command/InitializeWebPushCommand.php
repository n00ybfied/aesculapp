<?php
declare(strict_types=1);
namespace App\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
#[AsCommand(name:'app:push:initialize-key')]
final class InitializeWebPushCommand extends Command{
 public function __construct(#[Autowire('%kernel.project_dir%')]private readonly string $dir){parent::__construct();}
 protected function execute(InputInterface $input,OutputInterface $output):int{
  $path=$_ENV['WEB_PUSH_KEY_FILE']??$this->dir.'/var/private/web-push.json';
  if(is_file($path)){$output->writeln('Existing Web-Push key preserved.');return 0;}
  if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
  $keys=json_encode(\Minishlink\WebPush\VAPID::createVapidKeys(),JSON_THROW_ON_ERROR);
  $handle=fopen($path,'x');if(!$handle)return 1;chmod($path,0600);
  $written=fwrite($handle,$keys);fclose($handle);
  if($written!==strlen($keys))return 1;
  $output->writeln('Web-Push key created. Back up separately; make readable for PHP only.');return 0;
 }
}
