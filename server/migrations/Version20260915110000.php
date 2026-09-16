<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds configurable customer app startup notice.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenant ADD app_notice_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD app_notice_title VARCHAR(160) DEFAULT NULL, ADD app_notice_html LONGTEXT DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenant DROP app_notice_enabled, DROP app_notice_title, DROP app_notice_html'); }
}
