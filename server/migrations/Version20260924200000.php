<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow the pending staff confirmation appointment status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment CHANGE status status VARCHAR(32) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM appointment WHERE CHAR_LENGTH(status) > 20") > 0, 'Long appointment statuses already exist.');
        $this->addSql('ALTER TABLE appointment CHANGE status status VARCHAR(20) NOT NULL');
    }
}
