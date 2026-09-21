<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds global and appointment-type-specific booking blocks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE appointment_block (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, type_id INT DEFAULT NULL, starts_on VARCHAR(10) NOT NULL, ends_on VARCHAR(10) NOT NULL, starts_at VARCHAR(5) DEFAULT NULL, ends_at VARCHAR(5) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_APPOINTMENT_BLOCK_TENANT (tenant_id), INDEX IDX_APPOINTMENT_BLOCK_TYPE (type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE appointment_block ADD CONSTRAINT FK_APPOINTMENT_BLOCK_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE, ADD CONSTRAINT FK_APPOINTMENT_BLOCK_TYPE FOREIGN KEY (type_id) REFERENCES appointment_type (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE appointment_block');
    }
}
