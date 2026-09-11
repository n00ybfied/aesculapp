<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'web_push_subscription')]
class WebPushSubscription {
 #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id=null;
 #[ORM\ManyToOne,ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] public Tenant $tenant;
 #[ORM\ManyToOne,ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] public User $user;
 #[ORM\Column(length:64,unique:true)] public string $endpointHash;
 #[ORM\Column(type:'text')] public string $encryptedSubscription;
 public function __construct(Tenant $tenant,User $user,string $hash,string $encrypted){$this->tenant=$tenant;$this->user=$user;$this->endpointHash=$hash;$this->encryptedSubscription=$encrypted;}
}
