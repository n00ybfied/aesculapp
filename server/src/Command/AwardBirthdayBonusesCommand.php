<?php
declare(strict_types=1);
namespace App\Command;
use App\Entity\{PointAccount,PointTransaction,User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:award-birthday-bonuses',description:'Awards eligible birthday bonuses once per calendar year.')]
final class AwardBirthdayBonusesCommand extends Command { public function __construct(private EntityManagerInterface $em){parent::__construct();} protected function execute(InputInterface $input,OutputInterface $output):int{$today=new \DateTimeImmutable();$year=$today->format('Y');$users=$this->em->getRepository(User::class)->findAll();$count=0;foreach($users as $user){$birthday=$user->getBirthDate();if(!$birthday||$birthday->format('m-d')!==$today->format('m-d'))continue;foreach($this->em->getRepository(PointAccount::class)->findBy(['owner'=>$user]) as $account){$tenant=$account->getTenant();if($tenant->getBirthdayBonusPoints()<1)continue;$exists=$this->em->createQuery('SELECT COUNT(t.id) FROM App\\Entity\\PointTransaction t WHERE t.account=:account AND t.type=:type AND t.createdAt >= :start')->setParameter('account',$account)->setParameter('type','birthday_bonus')->setParameter('start',new \DateTimeImmutable($year.'-01-01'))->getSingleScalarResult();if((int)$exists>0)continue;$this->em->persist(new PointTransaction($account,$tenant->getBirthdayBonusPoints(),'birthday_bonus','Geburtstagsbonus'));$count++;}}$this->em->flush();$output->writeln("$count Geburtstagsboni gebucht.");return Command::SUCCESS;} }
