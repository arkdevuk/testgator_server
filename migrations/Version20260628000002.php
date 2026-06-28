<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ignored column to answer table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer ADD ignored BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer DROP COLUMN ignored');
    }
}
