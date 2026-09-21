<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds appointment reminder delivery state and customer push preference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ADD reminder_email_sent_at DATETIME DEFAULT NULL, ADD reminder_push_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD appointment_push_enabled TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment DROP reminder_email_sent_at, DROP reminder_push_sent_at');
        $this->addSql('ALTER TABLE tenant_membership DROP appointment_push_enabled');
    }
}
