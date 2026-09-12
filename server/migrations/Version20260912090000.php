<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260912090000 extends AbstractMigration {
 public function getDescription(): string { return 'Store the optional tenant website URL for the customer app iframe.'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD website_url VARCHAR(2048) DEFAULT NULL'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP website_url'); }
}
