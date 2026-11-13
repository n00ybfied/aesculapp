<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Medication catalogue, tenant stock snapshots and encrypted catalogue links in personal plans.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE medicine_catalogue (pzn CHAR(7) NOT NULL, name VARCHAR(180) NOT NULL, name_search VARCHAR(180) NOT NULL, pack VARCHAR(40) NOT NULL, delivery_code CHAR(1) NOT NULL, aep_cents INT DEFAULT NULL, kvp_cents INT DEFAULT NULL, avp_cents INT DEFAULT NULL, edition CHAR(4) NOT NULL, PRIMARY KEY(pzn), INDEX IDX_MEDICINE_NAME (name_search)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE medicine_stock (tenant_id INT NOT NULL, pzn CHAR(7) NOT NULL, stock_qty INT NOT NULL, reserved_qty INT NOT NULL, PRIMARY KEY(tenant_id, pzn), INDEX IDX_MEDICINE_STOCK_PZN (pzn), CONSTRAINT FK_MEDICINE_STOCK_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE medicine_import (source_key VARCHAR(80) NOT NULL, sha256 CHAR(64) NOT NULL, imported_at DATETIME NOT NULL, source_mtime DATETIME DEFAULT NULL, row_count INT NOT NULL, PRIMARY KEY(source_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE medication ADD encrypted_catalogue_pzn LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medication DROP encrypted_catalogue_pzn');
        $this->addSql('DROP TABLE medicine_stock');
        $this->addSql('DROP TABLE medicine_catalogue');
        $this->addSql('DROP TABLE medicine_import');
    }
}
