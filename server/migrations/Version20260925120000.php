<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add separate, one-time customer profile completion bonus configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD profile_completion_bonus_points INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD profile_completion_bonus_awarded_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP profile_completion_bonus_awarded_at');
        $this->addSql('ALTER TABLE tenant DROP profile_completion_bonus_points');
    }
}
