<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the password reset token foreign-key index name with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX idx_51c7e2c1a76ed395 TO IDX_6B7BA4B6A76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX IDX_6B7BA4B6A76ED395 TO idx_51c7e2c1a76ed395');
    }
}
