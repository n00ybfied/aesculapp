<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional age targeting to news push notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news_post ADD notification_min_age INT DEFAULT NULL, ADD notification_max_age INT DEFAULT NULL, ADD notification_include_missing_birth_date TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news_post DROP notification_min_age, DROP notification_max_age, DROP notification_include_missing_birth_date');
    }
}
