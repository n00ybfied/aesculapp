<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260911100000 extends AbstractMigration {
 public function getDescription(): string { return 'Add tenant media library metadata.'; }
 public function up(Schema $schema): void { $this->addSql('CREATE TABLE media_asset (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, owner_id INT DEFAULT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, visibility VARCHAR(30) NOT NULL, purpose VARCHAR(50) NOT NULL, width INT NOT NULL, height INT NOT NULL, file_size INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_MEDIA_TENANT (tenant_id), INDEX IDX_MEDIA_OWNER (owner_id), INDEX IDX_MEDIA_VISIBILITY (visibility), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'); $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_MEDIA_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE'); $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_MEDIA_OWNER FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE SET NULL'); }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE media_asset'); }
}
