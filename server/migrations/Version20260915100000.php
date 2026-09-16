<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds optional Google Maps link to pharmacy contact details.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD contact_google_maps_url VARCHAR(2048) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP contact_google_maps_url'); }
}
