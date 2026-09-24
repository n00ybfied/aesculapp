<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Temporarily reserves former usernames until all old access tokens have expired.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reserved_username (username_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, PRIMARY KEY(username_hash)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM reserved_username WHERE expires_at > NOW()') > 0, 'Gültige Benutzername-Reservierungen dürfen nicht entfernt werden.');
        $this->addSql('DROP TABLE reserved_username');
    }
}
