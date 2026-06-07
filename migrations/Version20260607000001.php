<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace answer.author (string) with answer.tester_id (FK to tester)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer DROP COLUMN author');
        $this->addSql('ALTER TABLE answer ADD tester_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_ANSWER_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ANSWER_TESTER ON answer (tester_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_ANSWER_TESTER');
        $this->addSql('ALTER TABLE answer DROP CONSTRAINT FK_ANSWER_TESTER');
        $this->addSql('ALTER TABLE answer DROP COLUMN tester_id');
        $this->addSql('ALTER TABLE answer ADD author VARCHAR(255) NOT NULL DEFAULT \'\'');
    }
}
