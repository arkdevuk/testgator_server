<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_picture_url column to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD profile_picture_url VARCHAR(512) NOT NULL DEFAULT '/assets/gator_avatar.png'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN profile_picture_url');
    }
}
