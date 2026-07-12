<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add file.is_public (boolean, default false).
 */
final class Version20260712000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_public (boolean, default false) to the file table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD is_public BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP COLUMN is_public');
    }
}
