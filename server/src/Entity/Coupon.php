<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'coupon')]
class Coupon
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Tenant $tenant;
    #[ORM\Column(length: 160)] private string $title;
    #[ORM\Column(length: 200)] private string $subtitle;
    #[ORM\Column(type: 'text')] private string $description;
    #[ORM\Column(length: 255)] private string $imagePath = '';
    #[ORM\Column(options: ['default' => true])] private bool $isVisible;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $availableFrom;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $availableUntil;

    public function __construct(Tenant $tenant, string $title, string $subtitle, string $description, string $imagePath, bool $isVisible, ?\DateTimeImmutable $availableFrom, ?\DateTimeImmutable $availableUntil) { $this->tenant=$tenant;$this->title=$title;$this->subtitle=$subtitle;$this->description=$description;$this->imagePath=$imagePath;$this->isVisible=$isVisible;$this->availableFrom=$availableFrom;$this->availableUntil=$availableUntil; }
    public function getId():?int{return $this->id;} public function getTenant():Tenant{return $this->tenant;} public function getTitle():string{return $this->title;} public function getSubtitle():string{return $this->subtitle;} public function getDescription():string{return $this->description;} public function getImagePath():string{return $this->imagePath;} public function isVisible():bool{return $this->isVisible;} public function getAvailableFrom():?\DateTimeImmutable{return $this->availableFrom;} public function getAvailableUntil():?\DateTimeImmutable{return $this->availableUntil;}
    public function isCurrentlyAvailable(\DateTimeImmutable $now):bool{return ($this->availableFrom===null||$this->availableFrom<=$now)&&($this->availableUntil===null||$this->availableUntil>=$now);}
    public function update(string $title,string $subtitle,string $description,string $imagePath,bool $isVisible,?\DateTimeImmutable $from,?\DateTimeImmutable $until):void{$this->title=$title;$this->subtitle=$subtitle;$this->description=$description;$this->imagePath=$imagePath;$this->isVisible=$isVisible;$this->availableFrom=$from;$this->availableUntil=$until;}
    public function setVisible(bool $visible):void{$this->isVisible=$visible;}
}
