<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/answers.
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
        $answer = $this->getTestAnswer();

        $data = $this->jsonRequest('GET', '/api/answers/' . $answer->getId(), null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('tester', $data);
        $this->assertJsonKey('state', $data);
        $this->assertJsonKey('comment', $data);
    }

    // ── POST /api/answers ─────────────────────────────────────────────────

    public function testCreateAnswerAsTeamUser(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Is the dashboard visible?']);
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        $data = $this->jsonRequest('POST', '/api/answers', [
            'tester' => '/api/testers/' . $tester->id,
            'state' => 'failed',
            'comment' => 'Dashboard shows a blank screen.',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('failed', $data['state']);
        self::assertStringContainsString((string)$tester->id, $data['tester']);
    }

    public function testDefaultStateIsPending(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        $data = $this->jsonRequest('POST', '/api/answers', [
            'tester' => '/api/testers/' . $tester->id,
            'comment' => 'No state provided.',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('pending', $data['state']);
    }

    public function testCreateAnswerRequiresAuth(): void
    {
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);

        $this->jsonRequest('POST', '/api/answers', [
            'comment' => 'Should fail',
            'question' => '/api/questions/' . $question->getId(),
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/answers/{id} ───────────────────────────────────────────

    public function testUpdateAnswer(): void
    {
        $token = $this->getTeamUserToken();
        $answer = $this->getTestAnswer();

        static::$client->request('PATCH', '/api/answers/' . $answer->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['state' => 'blocked', 'comment' => 'Blocked by upstream issue.'])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame('blocked', $data['state']);
        self::assertSame('Blocked by upstream issue.', $data['comment']);
    }

    // ── DELETE /api/answers/{id} ──────────────────────────────────────────

    public function testDeleteAnswer(): void
    {
        $token = $this->getTeamUserToken();
        $question = static::$em->getRepository(Question::class)
            ->findOneBy(['name' => 'Does the login work?']);
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        $created = $this->jsonRequest('POST', '/api/answers', [
            'tester' => '/api/testers/' . $tester->id,
            'state' => 'pending',
            'comment' => 'To be deleted',
            'question' => '/api/questions/' . $question->getId(),
        ], $token);
        $this->assertStatusCode(201);

        static::$client->request('DELETE', '/api/answers/' . $created['id'],
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertStatusCode(204);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function getTestAnswer(): Answer
    {
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        return static::$em->getRepository(Answer::class)
            ->findOneBy(['tester' => $tester]);
    }
}
