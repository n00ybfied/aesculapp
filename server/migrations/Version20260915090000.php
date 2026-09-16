<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds maintainable pharmacy contact details to tenants.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD contact_address VARCHAR(255) DEFAULT NULL, ADD contact_phone VARCHAR(80) DEFAULT NULL, ADD contact_email VARCHAR(255) DEFAULT NULL, ADD contact_opening_hours JSON DEFAULT NULL, ADD contact_latitude DOUBLE PRECISION DEFAULT NULL, ADD contact_longitude DOUBLE PRECISION DEFAULT NULL, ADD contact_map_zoom INT DEFAULT NULL, ADD contact_additional_html LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP contact_address, DROP contact_phone, DROP contact_email, DROP contact_opening_hours, DROP contact_latitude, DROP contact_longitude, DROP contact_map_zoom, DROP contact_additional_html');
    }
}
