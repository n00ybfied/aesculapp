<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store an append-only, tenant-scoped history of backend changes without form or chat contents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_change_log (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, actor_user_id INT NOT NULL, actor_name VARCHAR(160) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(100) DEFAULT NULL, action VARCHAR(10) NOT NULL, request_path VARCHAR(255) NOT NULL, occurred_at DATETIME NOT NULL, INDEX idx_admin_change_subject (tenant_id, entity_type, entity_id, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_change_log');
    }
}
