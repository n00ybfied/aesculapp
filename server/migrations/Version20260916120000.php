<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group coupon redemptions into atomic presentation batches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coupon_redemption ADD batch_id VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE coupon_redemption SET batch_id = LPAD(HEX(id), 32, '0') WHERE batch_id IS NULL");
        $this->addSql('ALTER TABLE coupon_redemption MODIFY batch_id VARCHAR(32) NOT NULL, ADD INDEX idx_coupon_redemption_batch (batch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coupon_redemption DROP INDEX idx_coupon_redemption_batch, DROP batch_id');
    }
}
