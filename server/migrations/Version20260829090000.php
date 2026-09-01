<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add tenant-scoped rewards.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reward (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(160) NOT NULL, subtitle VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, image_path VARCHAR(255) NOT NULL, required_points INT NOT NULL, is_visible TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_8389C8E19030F297 (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT FK_8389C8E19030F297 FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE reward'); }
}
