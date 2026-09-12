<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260912080000 extends AbstractMigration {
 public function getDescription(): string { return 'Track unread customer messages for the shared pharmacy team.'; }
 public function up(Schema $schema): void {
  $this->addSql('ALTER TABLE chat_message ADD staff_read_at DATETIME DEFAULT NULL');
  $this->addSql("UPDATE chat_message SET staff_read_at = created_at WHERE sender_role = 'customer'");
 }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE chat_message DROP staff_read_at'); }
}
