<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add tenant debug switch for duplicate loyalty receipt imports.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD allow_duplicate_receipt_imports TINYINT(1) NOT NULL DEFAULT 0'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP allow_duplicate_receipt_imports'); }
}
