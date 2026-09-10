<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link reversal point transactions to their original booking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE point_transaction ADD reversal_of_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE point_transaction ADD CONSTRAINT FK_POINT_TRANSACTION_REVERSAL FOREIGN KEY (reversal_of_id) REFERENCES point_transaction (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POINT_TRANSACTION_REVERSAL ON point_transaction (reversal_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE point_transaction DROP FOREIGN KEY FK_POINT_TRANSACTION_REVERSAL');
        $this->addSql('DROP INDEX UNIQ_POINT_TRANSACTION_REVERSAL ON point_transaction');
        $this->addSql('ALTER TABLE point_transaction DROP reversal_of_id');
    }
}
