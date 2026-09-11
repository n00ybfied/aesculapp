<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional tenant-specific receipt QR prefix.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD receipt_qr_prefix VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP receipt_qr_prefix');
    }
}
