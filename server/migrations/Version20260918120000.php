<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes weekdays from appointment types; availability owns scheduling weekdays.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment_type DROP bookable_weekdays');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment_type ADD bookable_weekdays JSON DEFAULT NULL');
    }
}
