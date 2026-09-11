<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_message')]
#[ORM\UniqueConstraint(name: 'uniq_chat_request', columns: ['conversation_id', 'request_id'])]
class ChatMessage {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] public ChatConversation $conversation;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] public User $sender;
    #[ORM\Column(length: 20)] public string $senderRole;
    #[ORM\Column(type: 'text')] public string $encryptedText;
    #[ORM\Column(type: 'text', length: 16777215, nullable: true)] public ?string $encryptedImage = null;
    #[ORM\Column(length: 36)] public string $requestId;
    #[ORM\Column] public \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)] public ?\DateTimeImmutable $customerReadAt = null;
    #[ORM\Column(options:['default'=>false])] public bool $pushPending = false;
    public function __construct(ChatConversation $conversation, User $sender, string $role, string $requestId) {
        $this->conversation = $conversation; $this->sender = $sender; $this->senderRole = $role;
        $this->requestId = $requestId; $this->createdAt = new \DateTimeImmutable();
    }
}
