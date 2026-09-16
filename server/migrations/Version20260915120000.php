<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds optional availability windows to rewards.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE reward ADD available_from DATETIME DEFAULT NULL, ADD available_until DATETIME DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE reward DROP available_from, DROP available_until'); }
}
