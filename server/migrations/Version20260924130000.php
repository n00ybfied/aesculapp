<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds appointment resource colors and per-staff customer-cancellation notices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment_resource ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD last_acknowledged_appointment_cancellation_id INT DEFAULT NULL');
        $this->addSql('CREATE TABLE appointment_cancellation_notice (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, appointment_id INT NOT NULL, occurred_at DATETIME NOT NULL, INDEX IDX_APPOINTMENT_CANCEL_NOTICE_TENANT (tenant_id), INDEX IDX_APPOINTMENT_CANCEL_NOTICE_APPOINTMENT (appointment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE appointment_cancellation_notice ADD CONSTRAINT FK_APPOINTMENT_CANCEL_NOTICE_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE, ADD CONSTRAINT FK_APPOINTMENT_CANCEL_NOTICE_APPOINTMENT FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM appointment_cancellation_notice') > 0, 'Kundenstorno-Hinweise müssen vor einem Rollback erhalten oder ausdrücklich migriert werden.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM appointment_resource WHERE color IS NOT NULL') > 0, 'Individuelle Personenfarben müssen vor einem Rollback erhalten oder ausdrücklich migriert werden.');
        $this->addSql('DROP TABLE appointment_cancellation_notice');
        $this->addSql('ALTER TABLE tenant_membership DROP last_acknowledged_appointment_cancellation_id');
        $this->addSql('ALTER TABLE appointment_resource DROP color');
    }
}
