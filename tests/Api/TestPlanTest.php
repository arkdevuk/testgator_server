<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Classes\TestPlanState;
use App\Entity\Release;
use App\Entity\TestPlan;
use DateTime;

/**
 * Tests for /api/test_plans.
 */
class TestPlanTest extends AbstractApiTestCase
{
    // ── GET /api/test_plans ───────────────────────────────────────────────

    public function testListTestPlansRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/test_plans');
        $this->assertStatusCode(401);
    }

    public function testListTestPlansReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/test_plans', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testFilterTestPlansByState(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest(
            'GET',
            '/api/test_plans?state=' . TestPlanState::PUBLISHED,
            null,
            $token
        );

        $this->assertStatusCode(200);
        foreach ($data['member'] as $plan) {
            self::assertSame(TestPlanState::PUBLISHED, $plan['state']);
        }
    }

    public function testFilterTestPlansByProject(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);
        $projectId = $release->getProject()->getId();

        $data = $this->jsonRequest(
            'GET',
            '/api/test_plans?release.project=' . $projectId,
            null,
            $token
        );

        $this->assertStatusCode(200);
        foreach ($data['member'] as $plan) {
            // Project fields are not in the testPlan:read normalization group, so
            // API Platform serializes the relation as a plain IRI string (e.g.
            // "/api/projects/3") rather than an embedded object.
            $projectRef = $plan['release']['project'];
            $projectIri = is_array($projectRef) ? ($projectRef['@id'] ?? '') : (string)$projectRef;
            self::assertSame('/api/projects/' . $projectId, $projectIri);
        }
    }

    public function testFilterTestPlansByName(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest(
            'GET',
            '/api/test_plans?name=Published',
            null,
            $token
        );

        $this->assertStatusCode(200);
        self::assertGreaterThan(0, $data['totalItems']);
        foreach ($data['member'] as $plan) {
            self::assertStringContainsStringIgnoringCase('Published', $plan['name']);
        }
    }

    // ── GET /api/test_plans/{id} ──────────────────────────────────────────

    public function testGetTestPlan(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $data = $this->jsonRequest('GET', '/api/test_plans/' . $plan->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame('Published Plan', $data['name']);
        $this->assertJsonKey('state', $data);
        $this->assertJsonKey('dueDate', $data);
        $this->assertJsonKey('questions', $data);
        $this->assertJsonKey('testersEnrolled', $data);
    }

    public function testGetTestPlanNotFound(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', '/api/test_plans/99999', null, $token);
        $this->assertStatusCode(404);
    }

    // ── POST /api/test_plans ──────────────────────────────────────────────

    public function testCreateTestPlan(): void
    {
        $token = $this->getTeamUserToken();
        $release = static::$em->getRepository(Release::class)
            ->findOneBy(['name' => '1.0.0']);

        $data = $this->jsonRequest('POST', '/api/test_plans', [
            'name' => 'New Regression Plan',
            'description' => 'Full regression for 1.0.0',
            'release' => '/api/releases/' . $release->getId(),
            'dueDate' => (new DateTime('+14 days'))->format(DateTime::ATOM),
            'state' => TestPlanState::DRAFT,
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('New Regression Plan', $data['name']);
        self::assertSame(TestPlanState::DRAFT, $data['state']);
    }

    public function testCreateTestPlanRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/test_plans', [
            'name' => 'Should Fail',
            'release' => '/api/releases/1',
            'dueDate' => (new DateTime('+7 days'))->format(DateTime::ATOM),
            'state' => TestPlanState::DRAFT,
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/test_plans/{id} ────────────────────────────────────────

    public function testUpdateTestPlanState(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Draft Plan']);

        static::$client->request('PATCH', '/api/test_plans/' . $plan->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['state' => TestPlanState::PUBLISHED])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertSame(TestPlanState::PUBLISHED, $data['state']);
    }

    // ── DELETE /api/test_plans/{id} ───────────────────────────────────────

    public function testDeleteTestPlan(): void
    {
        $token = $this->getTeamUserToken();
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Draft Plan']);

        static::$client->request('DELETE', '/api/test_plans/' . $plan->getId(),
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertStatusCode(204);
    }
}
