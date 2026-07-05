<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Question;
use App\Entity\TestPlan;

/**
 * Tests for /api/questions.
 */
class QuestionTest extends AbstractApiTestCase
{
    // ── GET /api/questions ────────────────────────────────────────────────

    public function testListQuestionsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/questions');
        $this->assertStatusCode(401);
    }

    public function testListQuestionsReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/questions', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testFilterQuestionsByPlan(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $data = $this->jsonRequest(
            'GET',
            '/api/questions?plan=' . $plan->getId(),
            null,
            $token
        );

        $this->assertStatusCode(200);
        self::assertGreaterThan(0, $data['totalItems']);
        foreach ($data['member'] as $q) {
            $planIri = is_array($q['plan']) ? $q['plan']['@id'] : $q['plan'];
            self::assertSame('/api/test_plans/' . $plan->getId(), $planIri);
        }
    }

    public function testFilterQuestionsByName(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest(
            'GET',
            '/api/questions?name=login',
            null,
            $token
        );

        $this->assertStatusCode(200);
        foreach ($data['member'] as $q) {
            self::assertStringContainsStringIgnoringCase('login', $q['name']);
        }
    }

    public function testListQuestionsOrderedByDisplayOrder(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $data = $this->jsonRequest(
            'GET',
            '/api/questions?plan=' . $plan->getId() . '&order[displayOrder]=asc',
            null,
            $token
        );

        $this->assertStatusCode(200);
        $orders = array_column($data['member'], 'displayOrder');
        $sorted = $orders;
        sort($sorted);
        self::assertSame($sorted, $orders);
    }

    // ── GET /api/questions/{id} ───────────────────────────────────────────

    public function testGetQuestion(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $data = $this->jsonRequest('GET', '/api/questions/' . $question->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame('Does the login work?', $data['name']);
        $this->assertJsonKey('content', $data);
        $this->assertJsonKey('answers', $data);
        $this->assertJsonKey('displayOrder', $data);
    }

    // ── POST /api/questions ───────────────────────────────────────────────

    public function testCreateQuestion(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $data = $this->jsonRequest('POST', '/api/questions', [
            'name' => 'Is the footer visible?',
            'content' => 'Scroll down and verify the footer is rendered.',
            'plan' => '/api/test_plans/' . $plan->getId(),
            'displayOrder' => 3,
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('Is the footer visible?', $data['name']);
        self::assertSame(3, $data['displayOrder']);
    }

    // ── PATCH /api/questions/{id} ─────────────────────────────────────────

    public function testUpdateQuestion(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        static::$client->request('PATCH', '/api/questions/' . $question->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['name' => 'Does the login page load correctly?'])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame('Does the login page load correctly?', $data['name']);
    }

    // ── DELETE /api/questions/{id} ────────────────────────────────────────

    public function testDeleteQuestion(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Is the dashboard visible?']);

        static::$client->request('DELETE', '/api/questions/' . $question->getId(),
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertStatusCode(204);
    }

    // ── GET /api/questions/{id}/stats ─────────────────────────────────────

    public function testQuestionStatsRequiresAuth(): void
    {
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $this->jsonRequest('GET', '/api/questions/' . $question->getId() . '/stats');
        $this->assertStatusCode(401);
    }

    public function testQuestionStatsShape(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $data = $this->jsonRequest('GET', '/api/questions/' . $question->getId() . '/stats', null, $token);

        $this->assertStatusCode(200);

        // Required keys
        foreach (['test_pass', 'test_pass_with_bugs', 'test_failed', 'test_blocked', 'test_pending', 'test_all_count', 'answer_rate', 'answers'] as $key) {
            $this->assertJsonKey($key, $data);
        }

        // Fixtures: tester1=pass, tester2=pass_with_bugs
        self::assertSame(1, $data['test_pass']);
        self::assertSame(1, $data['test_pass_with_bugs']);
        self::assertSame(0, $data['test_failed']);
        self::assertSame(0, $data['test_blocked']);
        self::assertSame(0, $data['test_pending']);
        self::assertSame(2, $data['test_all_count']);
        self::assertSame(100.0, $data['answer_rate']);
        self::assertCount(2, $data['answers']);

        $states = array_column($data['answers'], 'state');
        self::assertContains('pass', $states);
        self::assertContains('pass_with_bugs', $states);
        foreach ($data['answers'] as $a) {
            $this->assertJsonKey('answerId', $a);
            $this->assertJsonKey('state', $a);
        }
    }

    public function testQuestionStatsNotFound(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', '/api/questions/999999/stats', null, $token);
        $this->assertStatusCode(404);
    }

    public function testQuestionStatsEmptyQuestion(): void
    {
        $token = $this->getTeamUserToken();

        // Both fixture questions have answers ("Is the dashboard visible?" has a
        // FAILED and a BLOCKED answer), so create a fresh question with none.
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $question = new Question();
        $question->setName('Question without answers')
            ->setContent('No tester has answered this yet.')
            ->setPlan($plan)
            ->setDisplayOrder(99);
        static::$em->persist($question);
        static::$em->flush();

        $data = $this->jsonRequest('GET', '/api/questions/' . $question->getId() . '/stats', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(0, $data['test_all_count']);
        self::assertSame(0.0, $data['answer_rate']);
        self::assertCount(0, $data['answers']);
    }
}
