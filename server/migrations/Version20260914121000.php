<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repairs pending family point-sharing requests created before their status field was mapped.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE family_connection SET point_sharing_status = 'pending' WHERE point_sharing_status = 'none' AND point_sharing_requested_by_id IS NOT NULL AND point_sharing_accepted_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE family_connection SET point_sharing_status = 'none' WHERE point_sharing_status = 'pending' AND point_sharing_requested_by_id IS NOT NULL AND point_sharing_accepted_at IS NULL");
    }
}
