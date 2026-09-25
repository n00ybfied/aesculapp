<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Separate customer first and last names and track initial setup per tenant.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD first_name VARCHAR(80) DEFAULT NULL, ADD last_name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD customer_setup_completed_at DATETIME DEFAULT NULL');
        // Existing customers must not be sent through a new-account setup after deployment.
        $this->addSql('UPDATE tenant_membership SET customer_setup_completed_at = created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP customer_setup_completed_at');
        $this->addSql('ALTER TABLE app_user DROP first_name, DROP last_name');
    }
}
