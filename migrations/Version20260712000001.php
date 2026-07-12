<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace file.uploaded_by (string) with file.uploaded_by_id (FK to users).
 */
final class Version20260712000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace file.uploaded_by (string) with file.uploaded_by_id (FK to users)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP COLUMN uploaded_by');
        $this->addSql('ALTER TABLE file ADD uploaded_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_FILE_UPLOADED_BY FOREIGN KEY (uploaded_by_id) REFERENCES "users" (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FILE_UPLOADED_BY ON file (uploaded_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_FILE_UPLOADED_BY');
        $this->addSql('ALTER TABLE file DROP CONSTRAINT FK_FILE_UPLOADED_BY');
        $this->addSql('ALTER TABLE file DROP COLUMN uploaded_by_id');
        $this->addSql('ALTER TABLE file ADD uploaded_by VARCHAR(255) DEFAULT NULL');
    }
}
