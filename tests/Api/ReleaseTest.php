<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Project;
use App\Entity\Release;

/**
 * Tests for /api/releases.
 */
class ReleaseTest extends AbstractApiTestCase
{
    // ── GET /api/releases ─────────────────────────────────────────────────

    public function testListReleasesRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/releases');
        $this->assertStatusCode(401);
    }

    public function testListReleasesReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/releases', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testListReleasesFilterByProject(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        $data = $this->jsonRequest(
            'GET',
            '/api/releases?project='.$project->getId(),
            null,
            $token
        );

        $this->assertStatusCode(200);
        foreach ($data['member'] as $release) {
            self::assertSame($project->getId(), $release['project']['id']);
        }
    }

    // ── GET /api/releases/{id} ────────────────────────────────────────────

    public function testGetRelease(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        $data = $this->jsonRequest('GET', '/api/releases/'.$release->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame('1.0.0', $data['name']);
        $this->assertJsonKey('description', $data);
        $this->assertJsonKey('plans', $data);
    }

    public function testGetReleaseNotFound(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', '/api/releases/99999', null, $token);
        $this->assertStatusCode(404);
    }

    // ── GET /api/release_stats/{id} ───────────────────────────────────────

    public function testGetReleaseStats(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        $data = $this->jsonRequest('GET', '/api/release_stats/'.$release->getId(), null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('totalPlans', $data);
        $this->assertJsonKey('totalQuestions', $data);
        $this->assertJsonKey('totalResponded', $data);
        $this->assertJsonKey('percentage', $data);
        self::assertIsFloat($data['percentage']);
    }

    // ── POST /api/releases ────────────────────────────────────────────────

    public function testCreateRelease(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        $data = $this->jsonRequest('POST', '/api/releases', [
            'name' => '3.0.0',
            'description' => 'Third major release',
            'project' => '/api/projects/'.$project->getId(),
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('3.0.0', $data['name']);
    }

    public function testCreateReleaseRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/releases', [
            'name' => '4.0.0',
            'description' => 'Should fail',
            'project' => '/api/projects/1',
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/releases/{id} ──────────────────────────────────────────

    public function testUpdateRelease(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        static::$client->request('PATCH', '/api/releases/'.$release->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            json_encode(['name' => '1.0.1'])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame('1.0.1', $data['name']);
    }

    // ── DELETE /api/releases/{id} ─────────────────────────────────────────

    public function testDeleteRelease(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '2.0.0']);

        static::$client->request('DELETE', '/api/releases/'.$release->getId(),
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        $this->assertStatusCode(204);
    }
}
