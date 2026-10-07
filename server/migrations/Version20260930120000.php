<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record customer news-push consent and withdrawal with text version and timestamp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE news_push_consent_event (id INT AUTO_INCREMENT NOT NULL, membership_id INT NOT NULL, action VARCHAR(12) NOT NULL, text_version VARCHAR(32) NOT NULL, consent_text LONGTEXT NOT NULL, source VARCHAR(16) NOT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_news_push_consent_membership (membership_id, occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE news_push_consent_event ADD CONSTRAINT FK_NEWS_PUSH_CONSENT_MEMBERSHIP FOREIGN KEY (membership_id) REFERENCES tenant_membership (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE news_push_consent_event');
    }
}
