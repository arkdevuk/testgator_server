<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606191136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // No-op: Version20260606000001 already renamed status→state and set the default.
        // This diff was generated against a stale snapshot; the schema is already correct.
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE answer RENAME COLUMN state TO status");
        $this->addSql("ALTER TABLE answer ALTER COLUMN status DROP DEFAULT");
    }
}
