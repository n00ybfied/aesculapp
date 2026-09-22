<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds dashboard slider animation settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant ADD dashboard_slider_transition VARCHAR(10) NOT NULL DEFAULT 'slide', ADD dashboard_slider_animation_duration_ms INT NOT NULL DEFAULT 400, ADD dashboard_slider_delay_ms INT NOT NULL DEFAULT 6000");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP dashboard_slider_transition, DROP dashboard_slider_animation_duration_ms, DROP dashboard_slider_delay_ms');
    }
}
