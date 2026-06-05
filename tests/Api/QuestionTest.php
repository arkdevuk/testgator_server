<?php

namespace App\Tests\Api;

use App\Entity\Question;
use App\Entity\TestPlan;

/**
 * Tests for /api/questions
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
            self::assertSame('/api/test_plans/' . $plan->getId(), $q['plan']['@id']);
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
}
