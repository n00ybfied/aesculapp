<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260902140000 extends AbstractMigration { public function getDescription(): string { return 'Add tenant SMTP settings.'; } public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD smtp_host VARCHAR(255) DEFAULT NULL, ADD smtp_port INT DEFAULT NULL, ADD smtp_encryption VARCHAR(20) DEFAULT NULL, ADD smtp_username VARCHAR(255) DEFAULT NULL, ADD smtp_password_encrypted LONGTEXT DEFAULT NULL, ADD smtp_from VARCHAR(255) DEFAULT NULL'); } public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP smtp_host, DROP smtp_port, DROP smtp_encryption, DROP smtp_username, DROP smtp_password_encrypted, DROP smtp_from'); } }
