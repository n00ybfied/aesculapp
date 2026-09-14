<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260914100000 extends AbstractMigration {
 public function getDescription(): string { return 'Add tenant-controlled family point sharing feature flag and irreversible lock.'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD family_point_sharing_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD family_point_sharing_locked TINYINT(1) NOT NULL DEFAULT 0'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP family_point_sharing_enabled, DROP family_point_sharing_locked'); }
}
