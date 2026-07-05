<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\Classes\TestPlanState;
use App\Entity\Answer;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\Release;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Enum\AnswerState;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Deterministic fixtures for the test suite.
 *
 * All IDs / emails / passwords used in tests are sourced from constants here
 * so tests and fixtures stay in sync without magic strings.
 */
class TestFixtures extends Fixture
{
    // ── Users ─────────────────────────────────────────────────────────────
    public const USER_EMAIL = 'admin@testgator.test';
    public const USER_PASSWORD = 'Password1!';

    // ── Admin ─────────────────────────────────────────────────────────────
    public const ADMIN_EMAIL = 'superadmin@testgator.test';
    public const ADMIN_PASSWORD = 'AdminPass1!';
    public const REF_ADMIN = 'test-admin';

    // ── Testers ───────────────────────────────────────────────────────────
    public const TESTER_EMAIL = 'tester1@testgator.test';
    public const TESTER_EMAIL_2 = 'tester2@testgator.test';

    // ── Reference keys (used with getReference()) ─────────────────────────
    public const REF_PROJECT = 'test-project';
    public const REF_PROJECT_2 = 'test-project-2';
    public const REF_RELEASE = 'test-release';
    public const REF_RELEASE_2 = 'test-release-2';
    public const REF_TEST_PLAN = 'test-plan-published';
    public const REF_TEST_PLAN_2 = 'test-plan-draft';
    public const REF_QUESTION = 'test-question';
    public const REF_TESTER = 'test-tester';
    public const REF_TESTER_2 = 'test-tester-2';
    public const REF_ANSWER = 'test-answer';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        // ── User (team member) ────────────────────────────────────────────
        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, self::USER_PASSWORD));
        $manager->persist($user);

        // ── Admin (team member with ROLE_ADMIN) ───────────────────────────
        $admin = new User();
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $manager->persist($admin);
        $this->addReference(self::REF_ADMIN, $admin);

        // ── Testers (User entities with type TESTER) ─────────────────────
        $tester = User::createTester(self::TESTER_EMAIL);
        $manager->persist($tester);
        $this->addReference(self::REF_TESTER, $tester);

        $tester2 = User::createTester(self::TESTER_EMAIL_2);
        $manager->persist($tester2);
        $this->addReference(self::REF_TESTER_2, $tester2);

        // ── Projects ──────────────────────────────────────────────────────
        $project = new Project();
        $project->setName('Alpha Project')->setDescription('First test project');
        $project->addAllTester($tester);
        $manager->persist($project);
        $this->addReference(self::REF_PROJECT, $project);

        $project2 = new Project();
        $project2->setName('Beta Project')->setDescription('Second test project');
        $manager->persist($project2);
        $this->addReference(self::REF_PROJECT_2, $project2);

        // ── Releases ──────────────────────────────────────────────────────
        $release = new Release();
        $release->setName('1.0.0')->setProject($project)->setDescription('Initial release');
        $manager->persist($release);
        $this->addReference(self::REF_RELEASE, $release);

        $release2 = new Release();
        $release2->setName('2.0.0')->setProject($project)->setDescription('Major release');
        $manager->persist($release2);
        $this->addReference(self::REF_RELEASE_2, $release2);

        // ── TestPlans ─────────────────────────────────────────────────────
        $dueDate = new DateTime('+30 days');

        $plan = new TestPlan();
        $plan->setName('Published Plan')
            ->setDescription('A published test plan')
            ->setRelease($release)
            ->setDueDate($dueDate)
            ->setState(TestPlanState::PUBLISHED);
        $plan->addTestersEnrolled($tester);
        $manager->persist($plan);
        $this->addReference(self::REF_TEST_PLAN, $plan);

        $plan2 = new TestPlan();
        $plan2->setName('Draft Plan')
            ->setDescription('A draft test plan')
            ->setRelease($release)
            ->setDueDate($dueDate)
            ->setState(TestPlanState::DRAFT);
        $manager->persist($plan2);
        $this->addReference(self::REF_TEST_PLAN_2, $plan2);

        // ── Questions ─────────────────────────────────────────────────────
        $question = new Question();
        $question->setName('Does the login work?')
            ->setContent('Navigate to /login and verify credentials are accepted.')
            ->setPlan($plan)
            ->setDisplayOrder(1);
        $manager->persist($question);
        $this->addReference(self::REF_QUESTION, $question);

        $question2 = new Question();
        $question2->setName('Is the dashboard visible?')
            ->setContent('After login, verify the dashboard renders without errors.')
            ->setPlan($plan)
            ->setDisplayOrder(2);
        $manager->persist($question2);

        // ── Answers ───────────────────────────────────────────────────────

        // Question 1 — "Does the login work?"
        // tester1: login works cleanly
        $answer = new Answer();
        $answer->setTester($tester)
            ->setState(AnswerState::PASS)
            ->setComment('Login works as expected. Credentials accepted on first try.')
            ->setQuestion($question);
        $manager->persist($answer);
        $this->addReference(self::REF_ANSWER, $answer);

        // tester2: login works but shows a deprecation warning in the console
        $answer2 = new Answer();
        $answer2->setTester($tester2)
            ->setState(AnswerState::PASS_WITH_BUGS)
            ->setComment('Login succeeds but the browser console shows a JS deprecation warning on submit. Not blocking but should be investigated.')
            ->setQuestion($question);
        $manager->persist($answer2);

        // Question 2 — "Is the dashboard visible?"
        // tester1: dashboard fails to render — blank page after login
        $answer3 = new Answer();
        $answer3->setTester($tester)
            ->setState(AnswerState::FAILED)
            ->setComment('After login the dashboard shows a blank white page. No errors in the UI but the network tab shows a 500 on /api/dashboard/summary.')
            ->setQuestion($question2);
        $manager->persist($answer3);

        // tester2: could not even reach the dashboard — environment issue
        $answer4 = new Answer();
        $answer4->setTester($tester2)
            ->setState(AnswerState::BLOCKED)
            ->setComment('Unable to test: the staging environment is down (nginx 502). Will retry once the deployment is fixed.')
            ->setQuestion($question2);
        $manager->persist($answer4);

        $manager->flush();
    }
}
