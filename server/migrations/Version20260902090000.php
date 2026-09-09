<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260902090000 extends AbstractMigration { public function getDescription(): string { return 'Add configurable tenant initial points.'; } public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD initial_points INT NOT NULL DEFAULT 1230'); } public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP initial_points'); } }
