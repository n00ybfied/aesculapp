<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds dashboard slider autoplay setting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD dashboard_slider_autoplay TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP dashboard_slider_autoplay');
    }
}
