<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Staff area permissions and appointment confirmation/privacy settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD appointment_staff_confirmation_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD show_appointment_staff_names TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD permissions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE staff_invitation ADD permissions JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE staff_invitation DROP permissions');
        $this->addSql('ALTER TABLE tenant_membership DROP permissions');
        $this->addSql('ALTER TABLE tenant DROP appointment_staff_confirmation_enabled, DROP show_appointment_staff_names');
    }
}
