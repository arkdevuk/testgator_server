<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add tags (jsonb) to the users table.
 */
final class Version20260629000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tags jsonb column to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD tags jsonb NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN tags');
    }
}
