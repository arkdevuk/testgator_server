<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE settings (
                id       VARCHAR(255) NOT NULL,
                section  VARCHAR(100) NOT NULL,
                name     VARCHAR(100) NOT NULL,
                autoload BOOLEAN      NOT NULL DEFAULT FALSE,
                is_public BOOLEAN     NOT NULL DEFAULT FALSE,
                created  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
    }
}
