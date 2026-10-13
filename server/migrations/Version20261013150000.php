<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track five-minute presentation batches for prize-game coupon wins.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prize_game_spin ADD redemption_batch_id VARCHAR(32) DEFAULT NULL, ADD redemption_valid_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_prize_game_spin_redemption_batch ON prize_game_spin (redemption_batch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_prize_game_spin_redemption_batch ON prize_game_spin');
        $this->addSql('ALTER TABLE prize_game_spin DROP redemption_batch_id, DROP redemption_valid_until');
    }
}
