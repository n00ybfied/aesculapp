<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow staff-initiated conversations before customer chat consent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_conversation CHANGE consent_version consent_version VARCHAR(40) DEFAULT NULL, CHANGE consented_at consented_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM chat_conversation WHERE consent_version IS NULL OR consented_at IS NULL') > 0, 'Pending conversations cannot be migrated back without losing their consent state.');
        $this->addSql('ALTER TABLE chat_conversation CHANGE consent_version consent_version VARCHAR(40) NOT NULL, CHANGE consented_at consented_at DATETIME NOT NULL');
    }
}
