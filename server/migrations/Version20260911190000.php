<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260911190000 extends AbstractMigration {
 public function getDescription():string{return 'Add encrypted optional chat subject.';}
 public function up(Schema $schema):void{$this->addSql('ALTER TABLE chat_conversation ADD encrypted_subject LONGTEXT DEFAULT NULL');}
 public function down(Schema $schema):void{$this->addSql('ALTER TABLE chat_conversation DROP encrypted_subject');}
}
