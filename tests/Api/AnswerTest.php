<?php

namespace App\Tests\Api;

use App\Entity\Answer;
use App\Entity\Question;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/answers
 */
class AnswerTest extends AbstractApiTestCase
{
    // ── GET /api/answers ──────────────────────────────────────────────────

    public function testListAnswersRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/answers');
        $this->assertStatusCode(401);
    }

    public function testListAnswersReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/answers', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    // ── GET /api/answers/{id} ─────────────────────────────────────────────

    public function testGetAnswer(): void
    {
        $token = $this->getTeamUserToken();
        $answer = static::$em->getRepository(Answer::class)
            ->findOneBy(['author' => TestFixtures::TESTER_EMAIL]);

        $data = $this->jsonRequest('GET', '/api/answers/' . $answer->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::TESTER_EMAIL, $data['author']);
        $this->assertJsonKey('status', $data);
        $this->assertJsonKey('comment', $data);
    }

    // ── POST /api/answers ─────────────────────────────────────────────────

    public function testCreateAnswerAsTeamUser(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Is the dashboard visible?']);

        $data = $this->jsonRequest('POST', '/api/answers', [
            'author' => TestFixtures::USER_EMAIL,
            'status' => 'ko',
            'comment' => 'Dashboard shows a blank screen.',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('ko', $data['status']);
        self::assertSame(TestFixtures::USER_EMAIL, $data['author']);
    }

    public function testCreateAnswerRequiresAuth(): void
    {
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $this->jsonRequest('POST', '/api/answers', [
            'author' => 'anon@test.com',
            'status' => 'ok',
            'comment' => 'Should fail',
            'question' => '/api/questions/' . $question->getId(),
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/answers/{id} ───────────────────────────────────────────

    public function testUpdateAnswer(): void
    {
        $token = $this->getTeamUserToken();
        $answer = static::$em->getRepository(Answer::class)
            ->findOneBy(['author' => TestFixtures::TESTER_EMAIL]);

        static::$client->request('PATCH', '/api/answers/' . $answer->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['comment' => 'Updated: login works after cache clear.'])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame('Updated: login works after cache clear.', $data['comment']);
    }

    // ── DELETE /api/answers/{id} ──────────────────────────────────────────

    public function testDeleteAnswer(): void
    {
        // Create a fresh answer to delete so we don't disturb other tests
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $created = $this->jsonRequest('POST', '/api/answers', [
            'author' => 'delete-me@testgator.test',
            'status' => 'ok',
            'comment' => 'To be deleted',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);
        $this->assertStatusCode(201);

        $answerId = $created['id'];

        static::$client->request('DELETE', '/api/answers/' . $answerId,
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertStatusCode(204);
    }
}
