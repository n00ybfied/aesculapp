<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending staff invitations with password-setup tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE staff_invitation (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(160) NOT NULL, roles JSON NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', accepted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_STAFF_INVITATION_TENANT (tenant_id), INDEX idx_staff_invitation_token_hash (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE staff_invitation ADD CONSTRAINT FK_STAFF_INVITATION_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE staff_invitation');
    }
}
