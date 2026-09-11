<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260911155707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private encrypted chat conversations and messages.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_conversation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, consent_version VARCHAR(40) NOT NULL, consented_at DATETIME NOT NULL, tenant_id INT NOT NULL, customer_id INT NOT NULL, INDEX IDX_74654F689033212A (tenant_id), INDEX IDX_74654F689395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chat_message (id INT AUTO_INCREMENT NOT NULL, sender_role VARCHAR(20) NOT NULL, encrypted_text LONGTEXT NOT NULL, encrypted_image MEDIUMTEXT DEFAULT NULL, request_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, UNIQUE INDEX uniq_chat_request (conversation_id, request_id), INDEX IDX_FAB3FC169AC0396 (conversation_id), INDEX IDX_FAB3FC16F624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_conversation ADD CONSTRAINT FK_74654F689033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE chat_conversation ADD CONSTRAINT FK_74654F689395C3F3 FOREIGN KEY (customer_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_FAB3FC169AC0396 FOREIGN KEY (conversation_id) REFERENCES chat_conversation (id)');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_FAB3FC16F624B39D FOREIGN KEY (sender_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_conversation DROP FOREIGN KEY FK_74654F689033212A');
        $this->addSql('ALTER TABLE chat_conversation DROP FOREIGN KEY FK_74654F689395C3F3');
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_FAB3FC169AC0396');
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_FAB3FC16F624B39D');
        $this->addSql('DROP TABLE chat_conversation');
        $this->addSql('DROP TABLE chat_message');
    }
}
