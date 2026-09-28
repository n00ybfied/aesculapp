<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant-configured referral bonus and one-time, verified customer referral attribution.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD referral_bonus_points INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD referral_code VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TENANT_MEMBERSHIP_REFERRAL_CODE ON tenant_membership (referral_code)');
        $this->addSql('CREATE TABLE customer_referral (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, inviter_id INT NOT NULL, invitee_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', awarded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', awarded_points INT DEFAULT 0 NOT NULL, INDEX IDX_CUSTOMER_REFERRAL_TENANT (tenant_id), INDEX IDX_CUSTOMER_REFERRAL_INVITER (inviter_id), INDEX IDX_CUSTOMER_REFERRAL_INVITEE (invitee_id), UNIQUE INDEX uniq_customer_referral_invitee (tenant_id, invitee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_referral ADD CONSTRAINT FK_CUSTOMER_REFERRAL_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_referral ADD CONSTRAINT FK_CUSTOMER_REFERRAL_INVITER FOREIGN KEY (inviter_id) REFERENCES tenant_membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_referral ADD CONSTRAINT FK_CUSTOMER_REFERRAL_INVITEE FOREIGN KEY (invitee_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_referral');
        $this->addSql('DROP INDEX UNIQ_TENANT_MEMBERSHIP_REFERRAL_CODE ON tenant_membership');
        $this->addSql('ALTER TABLE tenant_membership DROP referral_code');
        $this->addSql('ALTER TABLE tenant DROP referral_bonus_points');
    }
}
