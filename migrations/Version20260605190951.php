<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260605190951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // No-op: Version20260605190343 (CREATE TABLE refresh_token, six
        // minutes earlier) already creates this table with the `extra`
        // column included, so `ALTER TABLE ... ADD extra` here was always
        // redundant — it just never ran against a database that didn't
        // already have some ad-hoc schema state papering over it. Left as a
        // no-op rather than deleted: other environments may already have
        // this version recorded in doctrine_migration_versions.
    }

    public function down(Schema $schema): void
    {
        // No-op — see up().
    }
}
