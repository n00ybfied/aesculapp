<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds configurable birthday greeting banner settings to tenants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD birthday_greeting_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD birthday_greeting_title VARCHAR(160) DEFAULT NULL, ADD birthday_greeting_text LONGTEXT DEFAULT NULL, ADD birthday_greeting_image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP birthday_greeting_enabled, DROP birthday_greeting_title, DROP birthday_greeting_text, DROP birthday_greeting_image_path');
    }
}
