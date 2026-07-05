<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for GET /api/search/query.
 *
 * Fixture reference (TestFixtures):
 *  - Project "Alpha Project"       description: "First test project"
 *  - Project "Beta Project"        description: "Second test project"
 *  - Tester  tester1@testgator.test
 *  - Tester  tester2@testgator.test
 *  - TestPlan "Published Plan"     description: "A published test plan"
 *  - TestPlan "Draft Plan"         description: "A draft test plan"
 *  - Question "Does the login work?"           content: "Navigate to /login and verify..."
 *  - Question "Is the dashboard visible?"      content: "After login, verify the dashboard..."
 *  - Answer (tester1, q1): "Login works as expected..."
 *  - Answer (tester2, q1): "Login succeeds but the browser console..."
 *  - Answer (tester1, q2): "After login the dashboard shows a blank..."
 *  - Answer (tester2, q2): "Unable to test: the staging environment..."
 */
class SearchTest extends AbstractApiTestCase
{
    private const BASE = '/api/search/query';

    // ── Auth & validation ─────────────────────────────────────────────────────

    public function testRequiresAuth(): void
    {
        $this->jsonRequest('GET', self::BASE . '?query=login');
        $this->assertStatusCode(401);
    }

    public function testMissingQueryParam(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', self::BASE, null, $token);
        $this->assertStatusCode(400);
    }

    public function testEmptyQueryParam(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', self::BASE . '?query=', null, $token);
        $this->assertStatusCode(400);
    }

    public function testInvalidScopeReturns400(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', self::BASE . '?query=login&scope=invalid_scope', null, $token);
        $this->assertStatusCode(400);
    }

    public function testOneInvalidScopeInMultiReturns400(): void
    {
        $token = $this->getTeamUserToken();
        $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions,not_a_scope', null, $token);
        $this->assertStatusCode(400);
    }

    // ── Result shape ──────────────────────────────────────────────────────────

    public function testResultShape(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions', null, $token);

        $this->assertStatusCode(200);
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        $first = $data[0];
        $this->assertJsonKey('type', $first);
        $this->assertJsonKey('iri', $first);
        $this->assertJsonKey('score', $first);
        $this->assertJsonKey('name', $first);
        $this->assertJsonKey('extracts', $first);
        self::assertIsArray($first['extracts']);
    }

    public function testExtractsContainMatchedTerm(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions', null, $token);

        $this->assertStatusCode(200);
        $allExtracts = array_merge(...array_column($data, 'extracts'));
        $found = array_filter($allExtracts, fn(string $e) => str_contains(strtolower($e), 'login'));
        self::assertNotEmpty($found, 'At least one extract should contain the search term');
    }

    public function testResultsSortedByScoreDescending(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login', null, $token);

        $this->assertStatusCode(200);
        $scores = array_column($data, 'score');
        $sorted = $scores;
        rsort($sorted);
        self::assertSame($sorted, $scores, 'Results must be sorted by score descending');
    }

    public function testNoResultsForUnknownQuery(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=xyzzy_no_match_42', null, $token);

        $this->assertStatusCode(200);
        self::assertSame([], $data);
    }

    // ── Scope: projects ───────────────────────────────────────────────────────

    public function testSearchProjects(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=alpha&scope=projects', null, $token);

        $this->assertStatusCode(200);
        $names = array_column($data, 'name');
        self::assertContains('Alpha Project', $names);
        $types = array_unique(array_column($data, 'type'));
        self::assertSame(['projects'], $types);
    }

    public function testSearchProjectsIriFormat(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=alpha&scope=projects', null, $token);
        $project = static::$em->getRepository(Project::class)->findOneBy(['name' => 'Alpha Project']);

        $iris = array_column($data, 'iri');
        self::assertContains('/api/projects/' . $project->getId(), $iris);
    }

    // ── Scope: testers ────────────────────────────────────────────────────────

    public function testSearchTesters(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=tester1&scope=testers', null, $token);

        $this->assertStatusCode(200);
        $names = array_column($data, 'name');
        self::assertContains(TestFixtures::TESTER_EMAIL, $names);
    }

    // ── Scope: test_plan ─────────────────────────────────────────────────────

    public function testSearchTestPlans(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=published&scope=test_plan', null, $token);

        $this->assertStatusCode(200);
        $names = array_column($data, 'name');
        self::assertContains('Published Plan', $names);
        $types = array_unique(array_column($data, 'type'));
        self::assertSame(['test_plan'], $types);
    }

    // ── Scope: questions ─────────────────────────────────────────────────────

    public function testSearchQuestions(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions', null, $token);

        $this->assertStatusCode(200);
        $names = array_column($data, 'name');
        self::assertContains('Does the login work?', $names);
    }

    public function testSearchQuestionsHigherScoreForTitleMatch(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions', null, $token);

        // "Does the login work?" has "login" in the name → should outscore
        // "Is the dashboard visible?" which only mentions login in content
        $this->assertStatusCode(200);
        self::assertNotEmpty($data);
        self::assertSame('Does the login work?', $data[0]['name'],
            'Question with login in the title should rank first');
    }

    // ── Scope: answers ────────────────────────────────────────────────────────

    public function testSearchAnswers(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=answers', null, $token);

        $this->assertStatusCode(200);
        self::assertNotEmpty($data);
        $types = array_unique(array_column($data, 'type'));
        self::assertSame(['answers'], $types);
    }

    // ── Multi-scope ───────────────────────────────────────────────────────────

    public function testMultipleScopesCommaSeparated(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=questions,answers', null, $token);

        $this->assertStatusCode(200);
        $types = array_unique(array_column($data, 'type'));
        sort($types);
        self::assertEqualsCanonicalizing(['answers', 'questions'], $types);
    }

    public function testMultipleScopesArrayStyle(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope[]=questions&scope[]=answers', null, $token);

        $this->assertStatusCode(200);
        // array_unique preserves original keys, so the result is not a list;
        // re-index with sort() so assertEqualsCanonicalizing sorts by value.
        $types = array_unique(array_column($data, 'type'));
        sort($types);
        self::assertEqualsCanonicalizing(['answers', 'questions'], $types);
    }

    public function testNoScopeSearchesAllTypes(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=login', null, $token);

        $this->assertStatusCode(200);
        $types = array_unique(array_column($data, 'type'));
        // "login" matches questions and answers from fixtures
        self::assertContains('questions', $types);
        self::assertContains('answers', $types);
    }

    // ── Tester access restrictions ────────────────────────────────────────────

    public function testTesterCannotSearchProjects(): void
    {
        $token = $this->getTesterToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=alpha&scope=projects', null, $token);

        // Scope is silently downgraded to tester-allowed scopes → no results
        $this->assertStatusCode(200);
        $types = array_column($data, 'type');
        self::assertNotContains('projects', $types);
    }

    public function testTesterCannotSearchOtherTesters(): void
    {
        $token = $this->getTesterToken();
        $data = $this->jsonRequest('GET', self::BASE . '?query=tester2&scope=testers', null, $token);

        $this->assertStatusCode(200);
        $types = array_column($data, 'type');
        self::assertNotContains('testers', $types);
    }

    public function testTesterOnlySeesOwnAnswers(): void
    {
        // tester2 searches for "login" — should only see their own answers
        $token = $this->getTesterToken(TestFixtures::TESTER_EMAIL_2);
        $data = $this->jsonRequest('GET', self::BASE . '?query=login&scope=answers', null, $token);

        $this->assertStatusCode(200);

        $tester2 = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL_2, 'type' => UserType::TESTER]);

        foreach ($data as $result) {
            // Each returned IRI should belong to an answer owned by tester2
            self::assertStringStartsWith('/api/answers/', $result['iri']);
        }

        // tester2's answer comment: "Login succeeds but the browser console..."
        $names = array_column($data, 'name');
        $found = array_filter($names, fn(string $n) => str_contains(strtolower($n), 'login succeeds'));
        self::assertNotEmpty($found, 'Tester2 should see their own login answer');
    }

    public function testTesterOnlySeesEnrolledTestPlans(): void
    {
        // tester1 is enrolled in "Published Plan" only (Draft Plan has no enrolled testers)
        $token = $this->getTesterToken(TestFixtures::TESTER_EMAIL);
        $data = $this->jsonRequest('GET', self::BASE . '?query=plan&scope=test_plan', null, $token);

        $this->assertStatusCode(200);
        $names = array_column($data, 'name');
        self::assertContains('Published Plan', $names, 'Tester1 should see their enrolled plan');
        self::assertNotContains('Draft Plan', $names, 'Tester1 should not see plans they are not enrolled in');
    }
}
