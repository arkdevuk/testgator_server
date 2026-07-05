<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nickname column to users table, backfilled from the local part of email';
    }

    public function up(Schema $schema): void
    {
        // Add nullable first so the backfill can run before enforcing NOT NULL
        $this->addSql('ALTER TABLE users ADD nickname VARCHAR(128) DEFAULT NULL');

        // Backfill: everything before the first '@' in the email address
        $this->addSql("UPDATE users SET nickname = SPLIT_PART(email, '@', 1)");

        // Now enforce NOT NULL
        $this->addSql('ALTER TABLE users ALTER COLUMN nickname SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN nickname');
    }
}
