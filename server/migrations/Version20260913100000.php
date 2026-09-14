<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add per-customer medication push preference and reminder times.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant_membership ADD medication_push_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD morning_reminder_time VARCHAR(5) NOT NULL DEFAULT '08:00', ADD noon_reminder_time VARCHAR(5) NOT NULL DEFAULT '12:00', ADD evening_reminder_time VARCHAR(5) NOT NULL DEFAULT '18:00', ADD night_reminder_time VARCHAR(5) NOT NULL DEFAULT '22:00'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP medication_push_enabled, DROP morning_reminder_time, DROP noon_reminder_time, DROP evening_reminder_time, DROP night_reminder_time');
    }
}
