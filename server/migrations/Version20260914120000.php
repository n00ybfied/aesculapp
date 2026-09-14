<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the default-enabled family access push preference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership ADD family_push_enabled TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP family_push_enabled');
    }
}
