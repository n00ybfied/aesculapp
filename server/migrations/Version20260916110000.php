<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record one-time coupon redemptions and cancellable presentation windows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE coupon_redemption (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, coupon_id INT NOT NULL, customer_id INT NOT NULL, created_at DATETIME NOT NULL, valid_until DATETIME NOT NULL, status VARCHAR(20) NOT NULL, INDEX IDX_4DC205229033212A (tenant_id), INDEX IDX_4DC2052266C5951B (coupon_id), INDEX IDX_4DC205229395C3F3 (customer_id), INDEX idx_coupon_redemption_customer (tenant_id, customer_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE coupon_redemption ADD CONSTRAINT FK_4DC205229033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE, ADD CONSTRAINT FK_4DC2052266C5951B FOREIGN KEY (coupon_id) REFERENCES coupon (id) ON DELETE CASCADE, ADD CONSTRAINT FK_4DC205229395C3F3 FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE coupon_redemption');
    }
}
