<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store loyalty receipt redemptions and prevent duplicate QR imports per tenant.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE loyalty_receipt_redemption (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, account_id INT NOT NULL, qr_hash VARCHAR(64) NOT NULL, receipt_number VARCHAR(160) NOT NULL, receipt_issued_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', eligible_cents INT NOT NULL, credited_points INT NOT NULL, redeemed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D1BF78E690326E8C (tenant_id), INDEX IDX_D1BF78E69B6B5FBA (account_id), UNIQUE INDEX uniq_loyalty_receipt_tenant_hash (tenant_id, qr_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE loyalty_receipt_redemption ADD CONSTRAINT FK_D1BF78E690326E8C FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE loyalty_receipt_redemption ADD CONSTRAINT FK_D1BF78E69B6B5FBA FOREIGN KEY (account_id) REFERENCES point_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE loyalty_receipt_redemption'); }
}
