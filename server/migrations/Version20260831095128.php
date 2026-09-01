<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831095128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_token (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, family VARCHAR(32) DEFAULT NULL, family_valid DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_C74F2195C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE active_redemption RENAME INDEX idx_a0d4a4179b6b5fba TO IDX_DC356D59B6B5FBA');
        $this->addSql('ALTER TABLE point_account RENAME INDEX idx_5f468e519030f297 TO IDX_CAE2AF689033212A');
        $this->addSql('ALTER TABLE point_account RENAME INDEX idx_5f468e517e3c61f9 TO IDX_CAE2AF687E3C61F9');
        $this->addSql('ALTER TABLE point_transaction RENAME INDEX idx_9fd3ad6a9b6b5fba TO IDX_44E83A049B6B5FBA');
        $this->addSql('ALTER TABLE reward RENAME INDEX idx_8389c8e19030f297 TO IDX_4ED172539033212A');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('ALTER TABLE active_redemption RENAME INDEX idx_dc356d59b6b5fba TO IDX_A0D4A4179B6B5FBA');
        $this->addSql('ALTER TABLE point_account RENAME INDEX idx_cae2af687e3c61f9 TO IDX_5F468E517E3C61F9');
        $this->addSql('ALTER TABLE point_account RENAME INDEX idx_cae2af689033212a TO IDX_5F468E519030F297');
        $this->addSql('ALTER TABLE point_transaction RENAME INDEX idx_44e83a049b6b5fba TO IDX_9FD3AD6A9B6B5FBA');
        $this->addSql('ALTER TABLE reward RENAME INDEX idx_4ed172539033212a TO IDX_8389C8E19030F297');
    }
}
