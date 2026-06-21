<?php

namespace App\Tests\Api;

use App\Entity\Project;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/projects
 */
class ProjectTest extends AbstractApiTestCase
{
    // ── GET /api/projects ─────────────────────────────────────────────────

    public function testListProjectsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/projects');
        $this->assertStatusCode(401);
    }

    public function testListProjectsReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/projects', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testListProjectsOrderById(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/projects?order[id]=asc', null, $token);

        $this->assertStatusCode(200);
        $ids = array_column($data['member'], 'id');
        $sorted = $ids;
        sort($sorted);
        self::assertSame($sorted, $ids, 'Projects should be ordered by id asc');
    }

    // ── GET /api/projects/{id} ────────────────────────────────────────────

    public function testGetProjectById(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        $data = $this->jsonRequest('GET', '/api/projects/' . $project->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame('Alpha Project', $data['name']);
        $this->assertJsonKey('description', $data);
        $this->assertJsonKey('totalReleases', $data);
    }

    public function testGetProjectNotFound(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', '/api/projects/99999', null, $token);
        $this->assertStatusCode(404);
    }

    // ── POST /api/projects ────────────────────────────────────────────────

    public function testCreateProject(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('POST', '/api/projects', [
            'name' => 'New Test Project',
            'description' => 'Created in test',
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('New Test Project', $data['name']);
        $this->assertJsonKey('id', $data);
    }

    public function testCreateProjectRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/projects', [
            'name' => 'Unauthorized Project',
            'description' => 'Should fail',
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/projects/{id} ──────────────────────────────────────────

    public function testUpdateProject(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        static::$client->request('PATCH', '/api/projects/' . $project->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['name' => 'Alpha Project Updated'])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame('Alpha Project Updated', $data['name']);
    }

    // ── DELETE /api/projects/{id} ─────────────────────────────────────────

    public function testDeleteProject(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Beta Project']);

        static::$client->request('DELETE', '/api/projects/' . $project->getId(),
            [], [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertStatusCode(204);

        // Verify gone
        $this->jsonRequest('GET', '/api/projects/' . $project->getId(), null, $token);
        $this->assertStatusCode(404);
    }

    public function testDeleteProjectRequiresAuth(): void
    {
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        static::$client->request('DELETE', '/api/projects/' . $project->getId());
        $this->assertStatusCode(401);
    }

    // ── GET /api/projects/{id}/stats ──────────────────────────────────────

    public function testProjectStatsRequiresAuth(): void
    {
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        $this->jsonRequest('GET', '/api/projects/' . $project->getId() . '/stats');
        $this->assertStatusCode(401);
    }

    public function testProjectStatsNotFound(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', '/api/projects/99999/stats', null, $token);
        $this->assertStatusCode(404);
    }

    public function testProjectStatsReturnsCorrectCounts(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Alpha Project']);

        $data = $this->jsonRequest('GET', '/api/projects/' . $project->getId() . '/stats', null, $token);

        $this->assertStatusCode(200);

        // Alpha Project fixture breakdown:
        //   releases  : release "1.0.0" + release2 "2.0.0"  → 2
        //   testPlans : plan (published) + plan2 (draft)     → 2
        //   testers   : tester1 enrolled in plan only        → 1
        //   answers   : 4 answers on 2 questions in plan     → 4
        self::assertSame(2, $data['releases'], 'releases count mismatch');
        self::assertSame(2, $data['testPlans'], 'testPlans count mismatch');
        self::assertSame(1, $data['testers'], 'testers count mismatch');
        self::assertSame(4, $data['answers'], 'answers count mismatch');
    }

    public function testProjectStatsEmptyProject(): void
    {
        $token = $this->getTeamUserToken();
        $project = static::$em->getRepository(Project::class)
            ->findOneBy(['name' => 'Beta Project']);

        $data = $this->jsonRequest('GET', '/api/projects/' . $project->getId() . '/stats', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(0, $data['releases']);
        self::assertSame(0, $data['testPlans']);
        self::assertSame(0, $data['testers']);
        self::assertSame(0, $data['answers']);
    }
}
