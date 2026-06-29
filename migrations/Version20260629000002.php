<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the tester_tag table.
 */
final class Version20260629000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tester_tag table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tester_tag (
                id          VARCHAR(128)  NOT NULL,
                label       VARCHAR(128)  NOT NULL,
                created_by_id UUID        NOT NULL,
                deleted     BOOLEAN       NOT NULL DEFAULT FALSE,
                created     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_tester_tag_created_by
                    FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE
            )
        SQL
        );

        $this->addSql('CREATE INDEX idx_tester_tag_created_by ON tester_tag (created_by_id)');
        $this->addSql('CREATE INDEX idx_tester_tag_deleted    ON tester_tag (deleted)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tester_tag');
    }
}
