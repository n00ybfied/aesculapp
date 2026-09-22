<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores up to four customer-selected footer navigation items per tenant membership.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership ADD footer_navigation_items JSON DEFAULT NULL');
        $this->addSql("UPDATE tenant_membership SET footer_navigation_items = JSON_MERGE_PRESERVE(IF(footer_home_enabled, JSON_ARRAY('home'), JSON_ARRAY()), IF(footer_chat_enabled, JSON_ARRAY('chat'), JSON_ARRAY()), IF(footer_rewards_enabled, JSON_ARRAY('rewards'), JSON_ARRAY()), IF(footer_website_enabled, JSON_ARRAY('website'), JSON_ARRAY()))");
        $this->addSql('ALTER TABLE tenant_membership MODIFY footer_navigation_items JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_membership DROP footer_navigation_items');
    }
}
