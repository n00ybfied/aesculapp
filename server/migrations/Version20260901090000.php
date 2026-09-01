<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds tenant branding asset paths.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant ADD logo_path VARCHAR(255) DEFAULT NULL, ADD square_logo_path VARCHAR(255) DEFAULT NULL, ADD favicon_path VARCHAR(255) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant DROP logo_path, DROP square_logo_path, DROP favicon_path');
    }
}
