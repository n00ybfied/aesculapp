<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add encrypted private medication plans for customers.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE medication (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, user_id INT NOT NULL, encrypted_name LONGTEXT NOT NULL, encrypted_dosage LONGTEXT DEFAULT NULL, encrypted_schedule LONGTEXT DEFAULT NULL, encrypted_notes LONGTEXT DEFAULT NULL, encrypted_image LONGTEXT DEFAULT NULL, refill_date DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_MEDICATION_TENANT (tenant_id), INDEX IDX_MEDICATION_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE medication ADD CONSTRAINT FK_MEDICATION_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE medication ADD CONSTRAINT FK_MEDICATION_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE medication'); }
}
