<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add project_picture_url and project_banner_url to the project table.
 */
final class Version20260629000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_picture_url and project_banner_url to project table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD project_picture_url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD project_banner_url  VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP COLUMN project_picture_url');
        $this->addSql('ALTER TABLE project DROP COLUMN project_banner_url');
    }
}
