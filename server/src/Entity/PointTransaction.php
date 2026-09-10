<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'point_transaction')]
class PointTransaction {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private PointAccount $account;
 #[ORM\Column] private int $points;
 #[ORM\Column(length:40)] private string $type;
 #[ORM\Column(length:255)] private string $label;
 #[ORM\Column] private \DateTimeImmutable $createdAt;
 #[ORM\OneToOne(targetEntity:self::class)] #[ORM\JoinColumn(name:'reversal_of_id',nullable:true,onDelete:'SET NULL')] private ?self $reversalOf=null;
 public function __construct(PointAccount $account,int $points,string $type,string $label){$this->account=$account;$this->points=$points;$this->type=$type;$this->label=$label;$this->createdAt=new \DateTimeImmutable();}
 public function getPoints():int{return $this->points;}
 public function getId():?int{return $this->id;} public function getAccount():PointAccount{return $this->account;} public function getType():string{return $this->type;} public function getLabel():string{return $this->label;} public function getCreatedAt():\DateTimeImmutable{return $this->createdAt;}
 public function setReversalOf(self $transaction):void{$this->reversalOf=$transaction;}
 public function getReversalOf():?self{return $this->reversalOf;}
}
