<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824100245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the prototype login identity to users.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD username VARCHAR(100) NOT NULL, ADD display_name VARCHAR(160) NOT NULL, ADD UNIQUE INDEX uniq_app_user_username (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP INDEX uniq_app_user_username, DROP username, DROP display_name');
    }
}
