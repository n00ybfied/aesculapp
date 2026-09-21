<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds privacy-conscious customer app analytics events.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_analytics_event (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, user_id INT NOT NULL, event_type VARCHAR(40) NOT NULL, subject_type VARCHAR(40) DEFAULT NULL, subject_id INT DEFAULT NULL, duration_seconds INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_analytics_tenant_type_date (tenant_id, event_type, created_at), INDEX idx_analytics_subject (tenant_id, subject_type, subject_id), INDEX IDX_D2E4A7FA903321 (tenant_id), INDEX IDX_D2E4A7FA6B3CA4B (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_analytics_event ADD CONSTRAINT FK_D2E4A7FA903321 FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_analytics_event ADD CONSTRAINT FK_D2E4A7FA6B3CA4B FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE app_analytics_event'); }
}
