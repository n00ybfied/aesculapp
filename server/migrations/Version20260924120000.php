<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows manually entered appointment guests without an app account.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment MODIFY customer_id INT DEFAULT NULL, ADD guest_name VARCHAR(160) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM appointment WHERE customer_id IS NULL') > 0, 'Gasttermine müssen vor einem Rollback erhalten oder ausdrücklich migriert werden.');
        $this->addSql('ALTER TABLE appointment MODIFY customer_id INT NOT NULL, DROP guest_name');
    }
}
