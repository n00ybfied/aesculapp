<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tenant dashboard slider images.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dashboard_slide (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, image_path VARCHAR(255) NOT NULL, link_url VARCHAR(2048) DEFAULT NULL, position INT NOT NULL, INDEX idx_dashboard_slide_tenant_position (tenant_id, position), INDEX IDX_9A16DD3A9033210A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dashboard_slide ADD CONSTRAINT FK_9A16DD3A9033210A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dashboard_slide');
    }
}
