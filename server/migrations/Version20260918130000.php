<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds tenant appointment booking and cancellation limits.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD appointment_booking_future_days INT NOT NULL DEFAULT 28, ADD appointment_cancellation_hours INT NOT NULL DEFAULT 24'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP appointment_booking_future_days, DROP appointment_cancellation_hours'); }
}
