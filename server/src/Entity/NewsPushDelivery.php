<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'news_push_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_news_push_post_user', columns: ['post_id', 'user_id'])]
class NewsPushDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NewsPost $post;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    public function __construct(NewsPost $post, User $user) { $this->post = $post; $this->user = $user; }
    public function getId(): ?int { return $this->id; }
    public function getPost(): NewsPost { return $this->post; }
    public function getUser(): User { return $this->user; }
    public function markSent(): void { $this->sentAt = new \DateTimeImmutable(); }
    public function markFailed(): void { ++$this->attempts; }
}
