<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260902091000 extends AbstractMigration { public function getDescription(): string { return 'Add birthday and tenant loyalty settings.'; } public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD birthday_bonus_points INT NOT NULL DEFAULT 200, ADD points_per_euro INT NOT NULL DEFAULT 10'); $this->addSql('ALTER TABLE app_user ADD birth_date DATE DEFAULT NULL'); } public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP birthday_bonus_points, DROP points_per_euro'); $this->addSql('ALTER TABLE app_user DROP birth_date'); } }
