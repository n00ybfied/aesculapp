<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add disabled-by-default debug override for editing prize games after first participation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD allow_started_prize_game_edits TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP allow_started_prize_game_edits');
    }
}
