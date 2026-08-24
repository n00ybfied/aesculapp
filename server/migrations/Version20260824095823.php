<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824095823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenants, global users and tenant memberships.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_app_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tenant (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(100) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_tenant_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tenant_membership (id INT AUTO_INCREMENT NOT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, tenant_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7EBE842D9033212A (tenant_id), INDEX IDX_7EBE842DA76ED395 (user_id), UNIQUE INDEX uniq_tenant_membership (tenant_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tenant_membership ADD CONSTRAINT FK_7EBE842D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenant_membership ADD CONSTRAINT FK_7EBE842DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tenant_membership DROP FOREIGN KEY FK_7EBE842D9033212A');
        $this->addSql('ALTER TABLE tenant_membership DROP FOREIGN KEY FK_7EBE842DA76ED395');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE tenant');
        $this->addSql('DROP TABLE tenant_membership');
    }
}
