<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260914110000 extends AbstractMigration {
 public function getDescription(): string { return 'Add independently consented family point sharing request state.'; }
 public function up(Schema $schema): void { $this->addSql("ALTER TABLE family_connection ADD point_sharing_status VARCHAR(16) NOT NULL DEFAULT 'none', ADD point_sharing_requested_by_id INT DEFAULT NULL, ADD point_sharing_accepted_at DATETIME DEFAULT NULL, ADD INDEX IDX_FAMILY_POINT_REQUESTER (point_sharing_requested_by_id)"); $this->addSql('ALTER TABLE family_connection ADD CONSTRAINT FK_FAMILY_POINT_REQUESTER FOREIGN KEY (point_sharing_requested_by_id) REFERENCES app_user (id) ON DELETE SET NULL'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE family_connection DROP FOREIGN KEY FK_FAMILY_POINT_REQUESTER'); $this->addSql('ALTER TABLE family_connection DROP point_sharing_status, DROP point_sharing_requested_by_id, DROP point_sharing_accepted_at'); }
}
