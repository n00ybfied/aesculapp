<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant-specific customer footer navigation preferences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership ADD footer_home_enabled TINYINT(1) NOT NULL DEFAULT 1, ADD footer_chat_enabled TINYINT(1) NOT NULL DEFAULT 1, ADD footer_rewards_enabled TINYINT(1) NOT NULL DEFAULT 1, ADD footer_website_enabled TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP footer_home_enabled, DROP footer_chat_enabled, DROP footer_rewards_enabled, DROP footer_website_enabled');
    }
}
