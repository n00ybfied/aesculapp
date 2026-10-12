<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store encrypted native push tokens per customer and tenant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE native_push_device (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, encrypted_token LONGTEXT NOT NULL, platform VARCHAR(16) NOT NULL, app_id VARCHAR(120) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_native_push_token (token_hash), INDEX idx_native_push_tenant_user (tenant_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE native_push_device ADD CONSTRAINT FK_NATIVE_PUSH_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE native_push_device ADD CONSTRAINT FK_NATIVE_PUSH_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE native_push_device');
    }
}
