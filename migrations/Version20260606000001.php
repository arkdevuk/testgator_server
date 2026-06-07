<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename answer.status to answer.state, change to enum (pass|failed|blocked|pending), default pending';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE answer RENAME COLUMN status TO state");
        $this->addSql("ALTER TABLE answer ALTER COLUMN state TYPE VARCHAR(10)");
        $this->addSql("UPDATE answer SET state = 'pending' WHERE state NOT IN ('pass', 'failed', 'blocked', 'pending')");
        $this->addSql("ALTER TABLE answer ALTER COLUMN state SET DEFAULT 'pending'");
        $this->addSql("ALTER TABLE answer ALTER COLUMN state SET NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE answer RENAME COLUMN state TO status");
        $this->addSql("ALTER TABLE answer ALTER COLUMN status DROP DEFAULT");
    }
}
