<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260911201000 extends AbstractMigration{
 public function getDescription():string{return 'Web-Push device subscriptions and queued reply notifications.';}
 public function up(Schema $schema):void{
  $this->addSql('ALTER TABLE chat_message ADD push_pending TINYINT(1) NOT NULL DEFAULT 0');
  $this->addSql('CREATE TABLE web_push_subscription (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, user_id INT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, encrypted_subscription LONGTEXT NOT NULL, UNIQUE INDEX uniq_push_endpoint (endpoint_hash), INDEX idx_push_tenant (tenant_id), INDEX idx_push_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
  $this->addSql('ALTER TABLE web_push_subscription ADD CONSTRAINT fk_push_tenant FOREIGN KEY (tenant_id) REFERENCES tenant(id) ON DELETE CASCADE, ADD CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE');
 }
 public function down(Schema $schema):void{$this->addSql('DROP TABLE web_push_subscription');$this->addSql('ALTER TABLE chat_message DROP push_pending');}
}
