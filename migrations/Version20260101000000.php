<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline: create every table that predates the migration chain.
 *
 * migrations/ never contained a full schema history — the earliest tracked
 * migration (Version20260605190343) only creates refresh_token. Every other
 * table (tester, users, file, project, release, test_plan, question, answer,
 * project_tester, test_plan_tester, answer_file, question_file) was
 * originally bootstrapped by running `doctrine:schema:update --force`
 * directly against a database (the same command `make db-0` still uses
 * today), so it was never captured as a migration. Later migrations only
 * ever ALTER these tables — Version20260606000001, for example, assumes
 * `answer` already exists and fails with "relation \"answer\" does not
 * exist" on a genuinely empty database.
 *
 * This migration recreates each of those tables in the exact shape they
 * were in immediately before the first migration that touches them —
 * columns added or changed later (e.g. answer.tester_id, answer.important,
 * users.type/active/otp/..., file.uploaded_by_id/is_public,
 * project.project_picture_url/project_banner_url) are deliberately left
 * out here; the existing incremental migrations add them right afterwards,
 * exactly as they always have on every environment that already has data.
 *
 * Guarded by a single check (does `answer` already exist?) so this is a
 * no-op on any database that was already bootstrapped some other way —
 * it only runs against a genuinely empty schema.
 */
final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Baseline: create tables that predate the migration chain (tester, users, file, project, release, test_plan, question, answer, project_tester, test_plan_tester, answer_file, question_file)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('answer')) {
            // Already bootstrapped some other way (e.g. an existing
            // environment that ran doctrine:schema:update directly) —
            // nothing to do.
            return;
        }

        // tester — original standalone tester accounts table. Merged into
        // users and dropped by Version20260611000001; recreated here only
        // so that migration (and every one before it that still references
        // the `tester` table) has something to operate on.
        $this->addSql(<<<'SQL'
            CREATE TABLE tester (
                id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                active BOOLEAN NOT NULL,
                otp VARCHAR(255) DEFAULT NULL,
                otp_try INT DEFAULT 0 NOT NULL,
                otp_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_active TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('COMMENT ON COLUMN tester.id IS \'(DC2Type:uuid)\'');

        // users — original team-account table, before the tester merge
        // (Version20260611000001) and the later nickname/profile/tags
        // columns (Version20260628000003, Version20260628000004,
        // Version20260629000001) were added.
        $this->addSql(<<<'SQL'
            CREATE TABLE "users" (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                src VARCHAR(255) NOT NULL DEFAULT 'app',
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
        $this->addSql('COMMENT ON COLUMN "users".id IS \'(DC2Type:uuid)\'');

        // file — original shape, before uploaded_by was replaced by
        // uploaded_by_id (Version20260712000001) and is_public was added
        // (Version20260712000002).
        $this->addSql(<<<'SQL'
            CREATE TABLE file (
                id UUID NOT NULL,
                "key" VARCHAR(255) NOT NULL,
                bucket TEXT NOT NULL,
                extension VARCHAR(255) NOT NULL,
                s3uuid TEXT DEFAULT NULL,
                bucket_url TEXT DEFAULT NULL,
                uploaded_by VARCHAR(255) DEFAULT NULL,
                created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('COMMENT ON COLUMN file.id IS \'(DC2Type:uuid)\'');

        // project — original shape, before project_picture_url /
        // project_banner_url were added (Version20260629000003).
        $this->addSql(<<<'SQL'
            CREATE TABLE project (
                id SERIAL NOT NULL,
                picture_id UUID DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_PROJECT_PICTURE ON project (picture_id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_PROJECT_PICTURE FOREIGN KEY (picture_id) REFERENCES file (id)');

        // release (quoted — RELEASE has special meaning in SQL's
        // SAVEPOINT grammar, so it's kept quoted everywhere it's used).
        $this->addSql(<<<'SQL'
            CREATE TABLE "release" (
                id SERIAL NOT NULL,
                project_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_RELEASE_PROJECT ON "release" (project_id)');
        $this->addSql('ALTER TABLE "release" ADD CONSTRAINT FK_RELEASE_PROJECT FOREIGN KEY (project_id) REFERENCES project (id)');

        // test_plan — never touched by any incremental migration, so this
        // matches the entity mapping's current, final shape exactly.
        $this->addSql(<<<'SQL'
            CREATE TABLE test_plan (
                id SERIAL NOT NULL,
                release_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                key VARCHAR(255) NOT NULL,
                state VARCHAR(255) NOT NULL DEFAULT 'draft',
                questions_order JSON NOT NULL,
                due_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                content TEXT DEFAULT NULL,
                created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_TEST_PLAN_RELEASE ON test_plan (release_id)');
        $this->addSql('ALTER TABLE test_plan ADD CONSTRAINT FK_TEST_PLAN_RELEASE FOREIGN KEY (release_id) REFERENCES "release" (id)');

        // question — never touched by any incremental migration either.
        $this->addSql(<<<'SQL'
            CREATE TABLE question (
                id SERIAL NOT NULL,
                plan_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                content TEXT DEFAULT NULL,
                display_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_QUESTION_PLAN ON question (plan_id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_QUESTION_PLAN FOREIGN KEY (plan_id) REFERENCES test_plan (id)');

        // answer — original shape: status (renamed to state in
        // Version20260606000001) and author (replaced by tester_id in
        // Version20260607000001) are the pre-migration columns; important
        // (Version20260614000001) and ignored (Version20260628000002) are
        // added later and deliberately left out here.
        $this->addSql(<<<'SQL'
            CREATE TABLE answer (
                id SERIAL NOT NULL,
                question_id INT NOT NULL,
                status VARCHAR(255) DEFAULT NULL,
                author VARCHAR(255) NOT NULL DEFAULT '',
                system_infos JSON DEFAULT NULL,
                comment TEXT NOT NULL,
                PRIMARY KEY (id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_ANSWER_QUESTION ON answer (question_id)');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_ANSWER_QUESTION FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE');

        // project_tester — join table for Project::$allTesters. tester_id
        // pointed at tester(id) originally; Version20260611000001
        // re-points it to users(id), both with ON DELETE CASCADE.
        $this->addSql(<<<'SQL'
            CREATE TABLE project_tester (
                project_id INT NOT NULL,
                tester_id UUID NOT NULL,
                PRIMARY KEY (project_id, tester_id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_PROJECT_TESTER_PROJECT ON project_tester (project_id)');
        $this->addSql('CREATE INDEX IDX_PROJECT_TESTER_TESTER ON project_tester (tester_id)');
        $this->addSql('ALTER TABLE project_tester ADD CONSTRAINT FK_PROJECT_TESTER_PROJECT FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE project_tester ADD CONSTRAINT FK_PROJECT_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE CASCADE');

        // test_plan_tester — join table for TestPlan::$testersEnrolled,
        // same tester_id history as project_tester above.
        $this->addSql(<<<'SQL'
            CREATE TABLE test_plan_tester (
                test_plan_id INT NOT NULL,
                tester_id UUID NOT NULL,
                PRIMARY KEY (test_plan_id, tester_id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_TEST_PLAN_TESTER_PLAN ON test_plan_tester (test_plan_id)');
        $this->addSql('CREATE INDEX IDX_TEST_PLAN_TESTER_TESTER ON test_plan_tester (tester_id)');
        $this->addSql('ALTER TABLE test_plan_tester ADD CONSTRAINT FK_TEST_PLAN_TESTER_PLAN FOREIGN KEY (test_plan_id) REFERENCES test_plan (id)');
        $this->addSql('ALTER TABLE test_plan_tester ADD CONSTRAINT FK_TEST_PLAN_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE CASCADE');

        // answer_file — join table for Answer::$files (default Doctrine
        // ManyToMany naming, no JoinTable override in the entity).
        $this->addSql(<<<'SQL'
            CREATE TABLE answer_file (
                answer_id INT NOT NULL,
                file_id UUID NOT NULL,
                PRIMARY KEY (answer_id, file_id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_ANSWER_FILE_ANSWER ON answer_file (answer_id)');
        $this->addSql('CREATE INDEX IDX_ANSWER_FILE_FILE ON answer_file (file_id)');
        $this->addSql('ALTER TABLE answer_file ADD CONSTRAINT FK_ANSWER_FILE_ANSWER FOREIGN KEY (answer_id) REFERENCES answer (id)');
        $this->addSql('ALTER TABLE answer_file ADD CONSTRAINT FK_ANSWER_FILE_FILE FOREIGN KEY (file_id) REFERENCES file (id)');

        // question_file — join table for Question::$files (default
        // Doctrine ManyToMany naming, no JoinTable override in the entity).
        $this->addSql(<<<'SQL'
            CREATE TABLE question_file (
                question_id INT NOT NULL,
                file_id UUID NOT NULL,
                PRIMARY KEY (question_id, file_id)
            )
            SQL
        );
        $this->addSql('CREATE INDEX IDX_QUESTION_FILE_QUESTION ON question_file (question_id)');
        $this->addSql('CREATE INDEX IDX_QUESTION_FILE_FILE ON question_file (file_id)');
        $this->addSql('ALTER TABLE question_file ADD CONSTRAINT FK_QUESTION_FILE_QUESTION FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE question_file ADD CONSTRAINT FK_QUESTION_FILE_FILE FOREIGN KEY (file_id) REFERENCES file (id)');
    }

    public function down(Schema $schema): void
    {
        // Best-effort teardown, reverse dependency order. Not meant to
        // perfectly restore a pre-baseline state (there wasn't one to
        // restore to) — this is here so `migrations:migrate prev` doesn't
        // leave the schema half-dropped.
        $this->addSql('DROP TABLE IF EXISTS question_file');
        $this->addSql('DROP TABLE IF EXISTS answer_file');
        $this->addSql('DROP TABLE IF EXISTS test_plan_tester');
        $this->addSql('DROP TABLE IF EXISTS project_tester');
        $this->addSql('DROP TABLE IF EXISTS answer');
        $this->addSql('DROP TABLE IF EXISTS question');
        $this->addSql('DROP TABLE IF EXISTS test_plan');
        $this->addSql('DROP TABLE IF EXISTS "release"');
        $this->addSql('DROP TABLE IF EXISTS project');
        $this->addSql('DROP TABLE IF EXISTS file');
        $this->addSql('DROP TABLE IF EXISTS "users"');
        $this->addSql('DROP TABLE IF EXISTS tester');
    }
}
