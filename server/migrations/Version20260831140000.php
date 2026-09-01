<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tenant-scoped pharmacy news posts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE news_post (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(160) NOT NULL, subtitle VARCHAR(255) NOT NULL, body_html LONGTEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, is_visible TINYINT(1) NOT NULL, published_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', show_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', show_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5F18D9CE9033211A (tenant_id), INDEX news_post_visibility_window (is_visible, show_from, show_until), INDEX news_post_published_at (published_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE news_post ADD CONSTRAINT FK_5F18D9CE9033211A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE news_post');
    }
}
