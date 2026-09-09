<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add tenant switch for visible customer debug output.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD show_customer_debug_output TINYINT(1) NOT NULL DEFAULT 0'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP show_customer_debug_output'); }
}
