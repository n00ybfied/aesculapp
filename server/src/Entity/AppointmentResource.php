<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
class AppointmentResource { #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id=null; #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private Tenant $tenant; #[ORM\Column(length:160)] private string $name; #[ORM\Column(length:20)] private string $kind='person'; #[ORM\Column(options:['default'=>true])] private bool $isActive=true; public function __construct(Tenant $tenant,string $name,string $kind='person'){$this->tenant=$tenant;$this->name=$name;$this->kind=$kind;} public function getId():?int{return $this->id;} public function getTenant():Tenant{return $this->tenant;} public function getName():string{return $this->name;} public function isActive():bool{return $this->isActive;} public function update(string $name,string $kind,bool $active):void{$this->name=$name;$this->kind=$kind;$this->isActive=$active;} }
