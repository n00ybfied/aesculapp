<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds single-use verification tokens for customer email changes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_change_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, tenant_id INT NOT NULL, new_email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, INDEX IDX_EMAIL_CHANGE_USER (user_id), INDEX IDX_EMAIL_CHANGE_TENANT (tenant_id), INDEX idx_email_change_token_hash (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE email_change_token ADD CONSTRAINT FK_EMAIL_CHANGE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE, ADD CONSTRAINT FK_EMAIL_CHANGE_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM email_change_token WHERE used_at IS NULL') > 0, 'Offene E-Mail-Änderungen dürfen nicht stillschweigend entfernt werden.');
        $this->addSql('DROP TABLE email_change_token');
    }
}
