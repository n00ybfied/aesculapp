<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional contact data and profile image path to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD phone VARCHAR(40) DEFAULT NULL, ADD street_address VARCHAR(160) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD city VARCHAR(120) DEFAULT NULL, ADD profile_image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP phone, DROP street_address, DROP postal_code, DROP city, DROP profile_image_path');
    }
}
