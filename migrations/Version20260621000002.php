<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add value column to settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings ADD value TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP COLUMN value');
    }
}
