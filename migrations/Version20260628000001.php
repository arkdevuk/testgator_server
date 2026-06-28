<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tester_annotation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE tester_annotation (
                id            UUID                        NOT NULL,
                relate_to_id  UUID                        NOT NULL,
                created_by_id UUID                        NOT NULL,
                content       TEXT                        NOT NULL,
                created       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        ');

        $this->addSql('
            ALTER TABLE tester_annotation
                ADD CONSTRAINT FK_TESTER_ANNOTATION_RELATE_TO
                    FOREIGN KEY (relate_to_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
                    NOT DEFERRABLE INITIALLY IMMEDIATE
        ');

        $this->addSql('
            ALTER TABLE tester_annotation
                ADD CONSTRAINT FK_TESTER_ANNOTATION_CREATED_BY
                    FOREIGN KEY (created_by_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
                    NOT DEFERRABLE INITIALLY IMMEDIATE
        ');

        $this->addSql('CREATE INDEX IDX_TESTER_ANNOTATION_RELATE_TO  ON tester_annotation (relate_to_id)');
        $this->addSql('CREATE INDEX IDX_TESTER_ANNOTATION_CREATED_BY ON tester_annotation (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tester_annotation DROP CONSTRAINT FK_TESTER_ANNOTATION_RELATE_TO');
        $this->addSql('ALTER TABLE tester_annotation DROP CONSTRAINT FK_TESTER_ANNOTATION_CREATED_BY');
        $this->addSql('DROP TABLE tester_annotation');
    }
}
