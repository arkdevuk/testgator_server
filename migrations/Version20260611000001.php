<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Merge the tester table into users.
 *
 * - users gains: type (USER|TESTER), active, otp, otp_try, otp_date,
 *   last_active, created, updated; password becomes nullable.
 * - tester rows are copied into users (ids preserved) with type = TESTER.
 *   If a tester email already exists in users, the existing user row is kept
 *   and all references are re-pointed to it.
 * - FKs on answer.tester_id, project_tester.tester_id and
 *   test_plan_tester.tester_id are re-pointed from tester to users.
 * - The tester table is dropped.
 */
final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge tester entity into users with a type column (USER|TESTER)';
    }

    public function up(Schema $schema): void
    {
        // 1. Extend the users table
        $this->addSql('ALTER TABLE "users" ADD type VARCHAR(20) DEFAULT \'USER\' NOT NULL');
        $this->addSql('ALTER TABLE "users" ADD active BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE "users" ADD otp VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "users" ADD otp_try INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "users" ADD otp_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "users" ADD last_active TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "users" ADD created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE "users" ADD updated TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE "users" ALTER password DROP NOT NULL');
        $this->addSql('CREATE INDEX IDX_USERS_TYPE ON "users" (type)');

        // 2. Copy testers into users (ids preserved); skip emails that already exist
        $this->addSql(<<<'SQL'
            INSERT INTO "users" (id, email, roles, password, src, type, active, otp, otp_try, otp_date, last_active, created, updated)
            SELECT t.id, t.email, '[]', NULL, 'app', 'TESTER', t.active, t.otp, t.otp_try, t.otp_date, t.last_active, t.created, t.updated
            FROM tester t
            WHERE NOT EXISTS (SELECT 1 FROM "users" u WHERE u.email = t.email)
        SQL
        );

        // 3. Re-point references of email-colliding testers to the surviving user row.
        //    (For all other testers u.id = t.id, so these statements are no-ops.)
        $this->addSql(<<<'SQL'
            UPDATE answer a SET tester_id = u.id
            FROM tester t
            JOIN "users" u ON u.email = t.email
            WHERE a.tester_id = t.id AND u.id <> t.id
        SQL
        );
        $this->addSql(<<<'SQL'
            UPDATE project_tester pt SET tester_id = u.id
            FROM tester t
            JOIN "users" u ON u.email = t.email
            WHERE pt.tester_id = t.id AND u.id <> t.id
              AND NOT EXISTS (
                SELECT 1 FROM project_tester pt2
                WHERE pt2.project_id = pt.project_id AND pt2.tester_id = u.id
              )
        SQL
        );
        $this->addSql(<<<'SQL'
            UPDATE test_plan_tester tpt SET tester_id = u.id
            FROM tester t
            JOIN "users" u ON u.email = t.email
            WHERE tpt.tester_id = t.id AND u.id <> t.id
              AND NOT EXISTS (
                SELECT 1 FROM test_plan_tester tpt2
                WHERE tpt2.test_plan_id = tpt.test_plan_id AND tpt2.tester_id = u.id
              )
        SQL
        );
        // drop leftover join rows that still point at a colliding tester id
        $this->addSql(<<<'SQL'
            DELETE FROM project_tester pt
            USING tester t
            JOIN "users" u ON u.email = t.email
            WHERE pt.tester_id = t.id AND u.id <> t.id
        SQL
        );
        $this->addSql(<<<'SQL'
            DELETE FROM test_plan_tester tpt
            USING tester t
            JOIN "users" u ON u.email = t.email
            WHERE tpt.tester_id = t.id AND u.id <> t.id
        SQL
        );

        // 4. Drop every FK that still references the tester table (names are
        //    schema-generated, so resolve them dynamically), then re-create
        //    the constraints against users.
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE r RECORD;
            BEGIN
              FOR r IN
                SELECT DISTINCT tc.table_name, tc.constraint_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu
                  ON ccu.constraint_name = tc.constraint_name
                 AND ccu.constraint_schema = tc.constraint_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND ccu.table_name = 'tester'
              LOOP
                EXECUTE format('ALTER TABLE %I DROP CONSTRAINT %I', r.table_name, r.constraint_name);
              END LOOP;
            END $$
        SQL
        );

        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_ANSWER_TESTER FOREIGN KEY (tester_id) REFERENCES "users" (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_tester ADD CONSTRAINT FK_PROJECT_TESTER_USER FOREIGN KEY (tester_id) REFERENCES "users" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_plan_tester ADD CONSTRAINT FK_TEST_PLAN_TESTER_USER FOREIGN KEY (tester_id) REFERENCES "users" (id) ON DELETE CASCADE');

        // 5. Drop the old tester table
        $this->addSql('DROP TABLE tester');
    }

    public function down(Schema $schema): void
    {
        // Best-effort rollback: re-create tester from users rows of type TESTER
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
                PRIMARY KEY(id)
            )
        SQL
        );
        $this->addSql(<<<'SQL'
            INSERT INTO tester (id, email, active, otp, otp_try, otp_date, last_active, created, updated)
            SELECT id, email, active, otp, otp_try, otp_date, last_active, created, updated
            FROM "users" WHERE type = 'TESTER'
        SQL
        );

        $this->addSql('ALTER TABLE answer DROP CONSTRAINT FK_ANSWER_TESTER');
        $this->addSql('ALTER TABLE project_tester DROP CONSTRAINT FK_PROJECT_TESTER_USER');
        $this->addSql('ALTER TABLE test_plan_tester DROP CONSTRAINT FK_TEST_PLAN_TESTER_USER');

        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_ANSWER_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_tester ADD CONSTRAINT FK_PROJECT_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_plan_tester ADD CONSTRAINT FK_TEST_PLAN_TESTER FOREIGN KEY (tester_id) REFERENCES tester (id) ON DELETE CASCADE');

        $this->addSql('DELETE FROM "users" WHERE type = \'TESTER\'');

        $this->addSql('DROP INDEX IDX_USERS_TYPE');
        $this->addSql('ALTER TABLE "users" DROP COLUMN type');
        $this->addSql('ALTER TABLE "users" DROP COLUMN active');
        $this->addSql('ALTER TABLE "users" DROP COLUMN otp');
        $this->addSql('ALTER TABLE "users" DROP COLUMN otp_try');
        $this->addSql('ALTER TABLE "users" DROP COLUMN otp_date');
        $this->addSql('ALTER TABLE "users" DROP COLUMN last_active');
        $this->addSql('ALTER TABLE "users" DROP COLUMN created');
        $this->addSql('ALTER TABLE "users" DROP COLUMN updated');
        $this->addSql('ALTER TABLE "users" ALTER password SET NOT NULL');
    }
}
