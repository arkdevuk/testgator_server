<?php

namespace App\Tests\Api;

use App\Entity\Answer;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\Release;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Access policies for TESTER accounts.
 *
 * These tests are the contract for what a tester can and cannot do through
 * the API. If one of them breaks, a policy has been broken — do not "fix"
 * the test without revisiting the policy first.
 *
 * Fixture topology (see TestFixtures):
 *  - tester1 (TESTER_EMAIL)  : enrolled in "Published Plan" (Alpha Project)
 *  - tester2 (TESTER_EMAIL_2): enrolled in nothing
 *  - "Draft Plan" has no enrolled testers
 *  - "Beta Project" has no releases/plans
 */
class TesterPolicyTest extends AbstractApiTestCase
{
    // ─────────────────────────────────────────────────────────────────────
    // /api/projects
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterOnlySeesProjectsWhereTheyHaveAPlan(): void
    {
        $token = $this->getTesterToken();
        $data = $this->jsonRequest('GET', '/api/projects', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(1, $data['totalItems']);
        self::assertSame('Alpha Project', $data['member'][0]['name']);
    }

    public function testTesterWithoutAssignmentSeesNoProjects(): void
    {
        $token = $this->getTesterToken(TestFixtures::TESTER_EMAIL_2);
        $data = $this->jsonRequest('GET', '/api/projects', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(0, $data['totalItems']);
    }

    public function testTesterCanGetAssignedProject(): void
    {
        $token = $this->getTesterToken();
        $project = $this->findProject('Alpha Project');

        $this->jsonRequest('GET', '/api/projects/' . $project->getId(), null, $token);
        $this->assertStatusCode(200);
    }

    private function findProject(string $name): Project
    {
        return static::$em->getRepository(Project::class)->findOneBy(['name' => $name]);
    }

    public function testTesterCannotGetUnrelatedProject(): void
    {
        $token = $this->getTesterToken();
        $project = $this->findProject('Beta Project');

        $this->jsonRequest('GET', '/api/projects/' . $project->getId(), null, $token);
        $this->assertStatusCode(404);
    }

    public function testTesterCannotCreateProject(): void
    {
        $token = $this->getTesterToken();
        $this->jsonRequest('POST', '/api/projects', [
            'name' => 'Sneaky project',
            'description' => 'Should be rejected',
        ], $token);

        $this->assertStatusCode(403);
    }

    public function testTesterCannotUpdateProject(): void
    {
        $token = $this->getTesterToken();
        $project = $this->findProject('Alpha Project');

        $this->patchRequest('/api/projects/' . $project->getId(), ['name' => 'Hacked'], $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/test_plans
    // ─────────────────────────────────────────────────────────────────────

    private function patchRequest(string $uri, array $payload, string $token): array
    {
        static::$client->request('PATCH', $uri, [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode($payload));

        $content = static::$client->getResponse()->getContent();

        return json_decode($content ?: '{}', true) ?? [];
    }

    public function testTesterCannotDeleteProject(): void
    {
        $token = $this->getTesterToken();
        $project = $this->findProject('Alpha Project');

        $this->deleteRequest('/api/projects/' . $project->getId(), $token);
        $this->assertStatusCode(403);
    }

    private function deleteRequest(string $uri, string $token): void
    {
        static::$client->request('DELETE', $uri, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
    }

    public function testTesterOnlySeesEnrolledTestPlans(): void
    {
        $token = $this->getTesterToken();
        $data = $this->jsonRequest('GET', '/api/test_plans', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(1, $data['totalItems']);
        self::assertSame('Published Plan', $data['member'][0]['name']);
    }

    public function testTesterWithoutAssignmentSeesNoTestPlans(): void
    {
        $token = $this->getTesterToken(TestFixtures::TESTER_EMAIL_2);
        $data = $this->jsonRequest('GET', '/api/test_plans', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(0, $data['totalItems']);
    }

    public function testTesterCannotGetUnenrolledTestPlan(): void
    {
        $token = $this->getTesterToken();
        $plan = $this->findPlan('Draft Plan');

        $this->jsonRequest('GET', '/api/test_plans/' . $plan->getId(), null, $token);
        $this->assertStatusCode(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stats
    // ─────────────────────────────────────────────────────────────────────

    private function findPlan(string $name): TestPlan
    {
        return static::$em->getRepository(TestPlan::class)->findOneBy(['name' => $name]);
    }

    public function testTesterCannotCreateTestPlan(): void
    {
        $token = $this->getTesterToken();
        $this->jsonRequest('POST', '/api/test_plans', [
            'name' => 'Sneaky plan',
            'description' => 'Should be rejected',
        ], $token);

        $this->assertStatusCode(403);
    }

    public function testTesterCannotUpdateTestPlan(): void
    {
        $token = $this->getTesterToken();
        $plan = $this->findPlan('Published Plan');

        $this->patchRequest('/api/test_plans/' . $plan->getId(), ['name' => 'Hacked'], $token);
        $this->assertStatusCode(403);
    }

    public function testTesterCannotDeleteTestPlan(): void
    {
        $token = $this->getTesterToken();
        $plan = $this->findPlan('Published Plan');

        $this->deleteRequest('/api/test_plans/' . $plan->getId(), $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/testers
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCanGetQuestionStatsOfEnrolledPlan(): void
    {
        $token = $this->getTesterToken();
        $question = $this->findQuestion('Does the login work?');

        $this->jsonRequest('GET', '/api/questions/' . $question->getId() . '/stats', null, $token);
        $this->assertStatusCode(200);
    }

    private function findQuestion(string $name): Question
    {
        return static::$em->getRepository(Question::class)->findOneBy(['name' => $name]);
    }

    public function testTesterCannotGetQuestionStatsOfUnenrolledPlan(): void
    {
        $token = $this->getTesterToken(TestFixtures::TESTER_EMAIL_2);
        $question = $this->findQuestion('Does the login work?');

        $this->jsonRequest('GET', '/api/questions/' . $question->getId() . '/stats', null, $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/working_hours
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCannotAccessReleaseStats(): void
    {
        $token = $this->getTesterToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        $this->jsonRequest('GET', '/api/release_stats/' . $release->getId(), null, $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/answers — reads
    // ─────────────────────────────────────────────────────────────────────

    public function testTeamUserCanAccessReleaseStats(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        $this->jsonRequest('GET', '/api/release_stats/' . $release->getId(), null, $token);
        $this->assertStatusCode(200);
    }

    public function testTesterCannotListTesters(): void
    {
        $token = $this->getTesterToken();
        $this->jsonRequest('GET', '/api/testers', null, $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/answers — POST
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCannotViewAnotherTester(): void
    {
        $token = $this->getTesterToken();
        $other = $this->findTester(TestFixtures::TESTER_EMAIL_2);

        $this->jsonRequest('GET', '/api/testers/' . $other->id, null, $token);
        $this->assertStatusCode(403);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/answers — PATCH
    // ─────────────────────────────────────────────────────────────────────

    private function findTester(string $email): User
    {
        return static::$em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::TESTER]);
    }

    public function testTesterCannotCreateTester(): void
    {
        $token = $this->getTesterToken();
        $this->jsonRequest('POST', '/api/testers', ['email' => 'new@tester.test'], $token);
        $this->assertStatusCode(403);
    }

    public function testTesterCanGetWorkingHours(): void
    {
        $token = $this->getTesterToken();
        $this->jsonRequest('GET', '/api/working_hours', null, $token);
        $this->assertStatusCode(200);
    }

    public function testTesterOnlySeesOwnAnswers(): void
    {
        $token = $this->getTesterToken();
        $tester = $this->findTester(TestFixtures::TESTER_EMAIL);

        $data = $this->jsonRequest('GET', '/api/answers', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(2, $data['totalItems']);
        foreach ($data['member'] as $answer) {
            self::assertStringContainsString((string)$tester->id, $answer['tester']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/answers — DELETE
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCannotGetAnotherTestersAnswer(): void
    {
        $token = $this->getTesterToken();
        $otherAnswer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL_2);

        $this->jsonRequest('GET', '/api/answers/' . $otherAnswer->getId(), null, $token);
        $this->assertStatusCode(404);
    }

    private function findAnswerOf(string $testerEmail): Answer
    {
        return static::$em->getRepository(Answer::class)
            ->findOneBy(['tester' => $this->findTester($testerEmail)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/demo/*
    // ─────────────────────────────────────────────────────────────────────

    public function testAnswerTesterIsForcedToCurrentUserOnPost(): void
    {
        $token = $this->getTesterToken();
        $self = $this->findTester(TestFixtures::TESTER_EMAIL);
        $other = $this->findTester(TestFixtures::TESTER_EMAIL_2);
        $question = $this->findQuestion('Does the login work?');

        // tester1 tries to post an answer in tester2's name
        $data = $this->jsonRequest('POST', '/api/answers', [
            'tester' => '/api/testers/' . $other->id,
            'state' => 'pass',
            'comment' => 'Trying to spoof the author.',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);

        $this->assertStatusCode(201);
        // the spoofed tester is ignored: the answer belongs to tester1
        self::assertStringContainsString((string)$self->id, $data['tester']);
        self::assertStringNotContainsString((string)$other->id, $data['tester']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Team users are NOT restricted
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCanUpdateOwnAnswer(): void
    {
        $token = $this->getTesterToken();
        $answer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL);

        $data = $this->patchRequest('/api/answers/' . $answer->getId(), [
            'state' => 'blocked',
            'comment' => 'Updated by its owner.',
        ], $token);

        $this->assertStatusCode(200);
        self::assertSame('blocked', $data['state']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function testTesterCannotReassignOwnAnswer(): void
    {
        $token = $this->getTesterToken();
        $answer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL);
        $other = $this->findTester(TestFixtures::TESTER_EMAIL_2);

        $this->patchRequest('/api/answers/' . $answer->getId(), [
            'tester' => '/api/testers/' . $other->id,
        ], $token);

        $this->assertStatusCode(403);
    }

    public function testTesterCannotMoveOwnAnswerToAnotherQuestion(): void
    {
        $token = $this->getTesterToken();
        $tester = $this->findTester(TestFixtures::TESTER_EMAIL);
        // tester1's answer on question 1
        $question1 = $this->findQuestion('Does the login work?');
        $question2 = $this->findQuestion('Is the dashboard visible?');
        $answer = static::$em->getRepository(Answer::class)
            ->findOneBy(['tester' => $tester, 'question' => $question1]);

        $this->patchRequest('/api/answers/' . $answer->getId(), [
            'question' => '/api/questions/' . $question2->getId(),
        ], $token);

        $this->assertStatusCode(403);
    }

    public function testTesterCannotPatchAnotherTestersAnswer(): void
    {
        $token = $this->getTesterToken();
        $otherAnswer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL_2);

        $this->patchRequest('/api/answers/' . $otherAnswer->getId(), [
            'comment' => 'Should never land.',
        ], $token);

        $this->assertStatusCode(404);
    }

    public function testTesterCannotDeleteOwnAnswer(): void
    {
        $token = $this->getTesterToken();
        $answer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL);

        $this->deleteRequest('/api/answers/' . $answer->getId(), $token);
        $this->assertStatusCode(403);
    }

    public function testTeamUserCanStillDeleteAnswers(): void
    {
        $token = $this->getTeamUserToken();
        $answer = $this->findAnswerOf(TestFixtures::TESTER_EMAIL);

        $this->deleteRequest('/api/answers/' . $answer->getId(), $token);
        $this->assertStatusCode(204);
    }

    public function testTesterCannotUseDemoEndpoints(): void
    {
        $token = $this->getTesterToken();
        $plan = $this->findPlan('Published Plan');

        $this->jsonRequest('POST', '/api/demo/add_demo_answer', [
            'tp' => $plan->getId(),
        ], $token);

        $this->assertStatusCode(403);
    }

    public function testTeamUserSeesEverything(): void
    {
        $token = $this->getTeamUserToken();

        $projects = $this->jsonRequest('GET', '/api/projects', null, $token);
        self::assertSame(2, $projects['totalItems']);

        $plans = $this->jsonRequest('GET', '/api/test_plans', null, $token);
        self::assertSame(2, $plans['totalItems']);

        $answers = $this->jsonRequest('GET', '/api/answers', null, $token);
        self::assertSame(4, $answers['totalItems']);

        $testers = $this->jsonRequest('GET', '/api/testers', null, $token);
        self::assertSame(2, $testers['totalItems']);
    }
}
