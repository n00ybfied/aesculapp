<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optionally assign appointment persons to staff users and link appointments to customer chats.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment_resource ADD assigned_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE appointment_resource ADD CONSTRAINT FK_APPOINTMENT_RESOURCE_ASSIGNED_USER FOREIGN KEY (assigned_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_APPOINTMENT_RESOURCE_ASSIGNED_USER ON appointment_resource (assigned_user_id)');
        $this->addSql('ALTER TABLE appointment ADD chat_conversation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_APPOINTMENT_CHAT_CONVERSATION FOREIGN KEY (chat_conversation_id) REFERENCES chat_conversation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_APPOINTMENT_CHAT_CONVERSATION ON appointment (chat_conversation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_APPOINTMENT_CHAT_CONVERSATION');
        $this->addSql('DROP INDEX IDX_APPOINTMENT_CHAT_CONVERSATION ON appointment');
        $this->addSql('ALTER TABLE appointment DROP chat_conversation_id');
        $this->addSql('ALTER TABLE appointment_resource DROP FOREIGN KEY FK_APPOINTMENT_RESOURCE_ASSIGNED_USER');
        $this->addSql('DROP INDEX IDX_APPOINTMENT_RESOURCE_ASSIGNED_USER ON appointment_resource');
        $this->addSql('ALTER TABLE appointment_resource DROP assigned_user_id');
    }
}
