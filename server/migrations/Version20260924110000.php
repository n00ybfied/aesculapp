<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores the last appointment viewed by each admin membership.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership ADD last_seen_appointment_id INT DEFAULT NULL');
        $this->addSql('UPDATE tenant_membership membership SET last_seen_appointment_id = (SELECT COALESCE(MAX(appointment.id), 0) FROM appointment WHERE appointment.tenant_id = membership.tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP last_seen_appointment_id');
    }
}
