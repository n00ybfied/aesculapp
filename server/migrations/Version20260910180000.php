<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link receipt credits to their loyalty receipt for receipt-date history display.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loyalty_receipt_redemption ADD point_transaction_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_LOYALTY_RECEIPT_POINT_TRANSACTION ON loyalty_receipt_redemption (point_transaction_id)');
        $this->addSql('ALTER TABLE loyalty_receipt_redemption ADD CONSTRAINT FK_LOYALTY_RECEIPT_POINT_TRANSACTION FOREIGN KEY (point_transaction_id) REFERENCES point_transaction (id) ON DELETE SET NULL');
        $this->addSql("UPDATE loyalty_receipt_redemption receipt INNER JOIN point_transaction transaction ON transaction.account_id = receipt.account_id AND transaction.type = 'receipt_credit' AND transaction.label = CONCAT('Punktefähiger Einkauf ', receipt.receipt_number) AND transaction.points = receipt.credited_points SET receipt.point_transaction_id = transaction.id WHERE receipt.point_transaction_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loyalty_receipt_redemption DROP FOREIGN KEY FK_LOYALTY_RECEIPT_POINT_TRANSACTION');
        $this->addSql('DROP INDEX UNIQ_LOYALTY_RECEIPT_POINT_TRANSACTION ON loyalty_receipt_redemption');
        $this->addSql('ALTER TABLE loyalty_receipt_redemption DROP point_transaction_id');
    }
}
