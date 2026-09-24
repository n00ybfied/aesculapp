<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store per-pharmacy email and push message templates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_template (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, template_key VARCHAR(100) NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, INDEX IDX_NOTIFICATION_TEMPLATE_TENANT (tenant_id), UNIQUE INDEX uniq_notification_template_tenant_key (tenant_id, template_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification_template ADD CONSTRAINT FK_NOTIFICATION_TEMPLATE_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_template');
    }
}
