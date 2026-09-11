<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260911200000 extends AbstractMigration {
 public function getDescription():string{return 'Track unread customer chat replies; treat historical messages as read.';}
 public function up(Schema $schema):void{
  $this->addSql('ALTER TABLE chat_message ADD customer_read_at DATETIME DEFAULT NULL');
  $this->addSql('UPDATE chat_message SET customer_read_at = created_at');
 }
 public function down(Schema $schema):void{$this->addSql('ALTER TABLE chat_message DROP customer_read_at');}
}
