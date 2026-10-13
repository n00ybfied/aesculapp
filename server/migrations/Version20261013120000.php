<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant-scoped prize wheels, immutable spins and separately redeemable won coupons.';
    }

    public function up(Schema $schema): void
    {
        // MySQL DDL is not transactional. These guards allow a safe retry after a partial first run.
        if (!$schema->hasTable('prize_game')) {
            $this->addSql("CREATE TABLE prize_game (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(180) NOT NULL, visible TINYINT(1) NOT NULL, available_from DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_until DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', participation_mode VARCHAR(20) NOT NULL, spin_limit INT NOT NULL, no_win_enabled TINYINT(1) NOT NULL, sold_out_text LONGTEXT NOT NULL, sold_out_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_prize_game_visibility (tenant_id, visible, available_from, available_until), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
            $this->addSql('ALTER TABLE prize_game ADD CONSTRAINT FK_PRIZE_GAME_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        }
        if (!$schema->hasTable('prize_game_prize')) {
            $this->addSql('CREATE TABLE prize_game_prize (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, title VARCHAR(180) NOT NULL, wheel_label VARCHAR(40) NOT NULL, kind VARCHAR(20) NOT NULL, points INT NOT NULL, probability_bp INT NOT NULL, stock_limit INT DEFAULT NULL, awarded_count INT NOT NULL, position INT NOT NULL, INDEX idx_prize_game_prize_game (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE prize_game_prize ADD CONSTRAINT FK_PRIZE_GAME_PRIZE_GAME FOREIGN KEY (game_id) REFERENCES prize_game (id) ON DELETE CASCADE');
        }
        if (!$schema->hasTable('prize_game_spin')) {
            $this->addSql("CREATE TABLE prize_game_spin (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, customer_id INT NOT NULL, prize_id INT DEFAULT NULL, redeemed_by_id INT DEFAULT NULL, request_id VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', prize_title VARCHAR(180) DEFAULT NULL, prize_kind VARCHAR(20) DEFAULT NULL, points INT NOT NULL, coupon_code VARCHAR(24) DEFAULT NULL, redeemed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_prize_game_spin_request (game_id, customer_id, request_id), UNIQUE INDEX uniq_prize_game_coupon_code (coupon_code), INDEX idx_prize_game_spin_customer (game_id, customer_id, created_at), INDEX IDX_PRIZE_GAME_SPIN_PRIZE (prize_id), INDEX IDX_PRIZE_GAME_SPIN_REDEEMER (redeemed_by_id), INDEX IDX_PRIZE_GAME_SPIN_CUSTOMER_FK (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE prize_game_spin ADD CONSTRAINT FK_PRIZE_GAME_SPIN_GAME FOREIGN KEY (game_id) REFERENCES prize_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prize_game_spin ADD CONSTRAINT FK_PRIZE_GAME_SPIN_CUSTOMER FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prize_game_spin ADD CONSTRAINT FK_PRIZE_GAME_SPIN_PRIZE FOREIGN KEY (prize_id) REFERENCES prize_game_prize (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE prize_game_spin ADD CONSTRAINT FK_PRIZE_GAME_SPIN_REDEEMER FOREIGN KEY (redeemed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE prize_game_spin');
        $this->addSql('DROP TABLE prize_game_prize');
        $this->addSql('DROP TABLE prize_game');
    }
}
