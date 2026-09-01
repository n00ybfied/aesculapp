<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'active_redemption')]
class ActiveRedemption {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private PointAccount $account;
 #[ORM\Column] private int $points;
 #[ORM\Column(length:255)] private string $summary;
 #[ORM\Column] private \DateTimeImmutable $validUntil;
 #[ORM\Column(length:20)] private string $status='active';
 public function __construct(PointAccount $account,int $points,string $summary){$this->account=$account;$this->points=$points;$this->summary=$summary;$this->validUntil=(new \DateTimeImmutable())->modify('+5 minutes');}
 public function getId():?int{return $this->id;} public function getAccount():PointAccount{return $this->account;} public function getPoints():int{return $this->points;} public function getSummary():string{return $this->summary;} public function getValidUntil():\DateTimeImmutable{return $this->validUntil;} public function isActive():bool{return $this->status==='active'&&$this->validUntil>new \DateTimeImmutable();} public function cancel():void{$this->status='cancelled';}
}
