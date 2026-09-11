<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260911210000 extends AbstractMigration {
 public function getDescription(): string { return 'Store customer notification preferences per tenant membership.'; }
 public function up(Schema $schema): void {
  $this->addSql('ALTER TABLE tenant_membership ADD newsletter_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD chat_push_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD reward_push_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD news_push_enabled TINYINT(1) NOT NULL DEFAULT 0');
 }
 public function down(Schema $schema): void {
  $this->addSql('ALTER TABLE tenant_membership DROP newsletter_enabled, DROP chat_push_enabled, DROP reward_push_enabled, DROP news_push_enabled');
 }
}
