<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the assigned staff member stable when a person record changes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ADD assigned_user_id INT DEFAULT NULL');
        $this->addSql('UPDATE appointment a JOIN appointment_resource r ON a.resource_id = r.id SET a.assigned_user_id = r.assigned_user_id');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_APPOINTMENT_ASSIGNED_USER FOREIGN KEY (assigned_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_APPOINTMENT_ASSIGNED_USER ON appointment (assigned_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_APPOINTMENT_ASSIGNED_USER');
        $this->addSql('DROP INDEX IDX_APPOINTMENT_ASSIGNED_USER ON appointment');
        $this->addSql('ALTER TABLE appointment DROP assigned_user_id');
    }
}
