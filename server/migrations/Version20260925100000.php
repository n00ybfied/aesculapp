<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Customer salutation, news categories and queued news notifications.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD salutation VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenant_membership ADD news_category_ids JSON DEFAULT NULL');
        $this->addSql("UPDATE tenant_membership SET news_category_ids = '[]'");
        $this->addSql('ALTER TABLE tenant_membership MODIFY news_category_ids JSON NOT NULL');
        $this->addSql('ALTER TABLE news_post ADD category_ids JSON DEFAULT NULL, ADD notification_salutations JSON DEFAULT NULL, ADD news_push_queued_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE news_post SET category_ids = '[]', notification_salutations = '[]'");
        $this->addSql('ALTER TABLE news_post MODIFY category_ids JSON NOT NULL, MODIFY notification_salutations JSON NOT NULL');
        $this->addSql('CREATE TABLE news_category (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, name VARCHAR(100) NOT NULL, INDEX IDX_4F72BA909033212A (tenant_id), UNIQUE INDEX uniq_news_category_tenant_name (tenant_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE news_category ADD CONSTRAINT FK_NEWS_CATEGORY_TENANT FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE news_push_delivery (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, sent_at DATETIME DEFAULT NULL, attempts INT DEFAULT 0 NOT NULL, INDEX IDX_6326D0C4B89032C (post_id), INDEX IDX_6326D0CA76ED395 (user_id), UNIQUE INDEX uniq_news_push_post_user (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE news_push_delivery ADD CONSTRAINT FK_NEWS_PUSH_POST FOREIGN KEY (post_id) REFERENCES news_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE news_push_delivery ADD CONSTRAINT FK_NEWS_PUSH_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE news_push_delivery');
        $this->addSql('DROP TABLE news_category');
        $this->addSql('ALTER TABLE news_post DROP category_ids, DROP notification_salutations, DROP news_push_queued_at');
        $this->addSql('ALTER TABLE tenant_membership DROP news_category_ids');
        $this->addSql('ALTER TABLE app_user DROP salutation');
    }
}
