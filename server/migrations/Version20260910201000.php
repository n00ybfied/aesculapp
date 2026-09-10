<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use Doctrine naming for the point transaction reversal constraint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE point_transaction RENAME INDEX uniq_point_transaction_reversal TO UNIQ_44E83A0429A0BB4E');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE point_transaction RENAME INDEX UNIQ_44E83A0429A0BB4E TO uniq_point_transaction_reversal');
    }
}
