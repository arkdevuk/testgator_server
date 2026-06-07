<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen answer.state to VARCHAR(20) to accommodate pass_with_bugs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer ALTER COLUMN state TYPE VARCHAR(20)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer ALTER COLUMN state TYPE VARCHAR(10)');
    }
}
