<?php

namespace App\Services;

use App\Entity\Answer;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;

class SearchService
{
    public const SCOPE_PROJECTS = 'projects';
    public const SCOPE_TESTERS = 'testers';
    public const SCOPE_TEST_PLAN = 'test_plan';
    public const SCOPE_QUESTIONS = 'questions';
    public const SCOPE_ANSWERS = 'answers';

    public const ALL_SCOPES = [
        self::SCOPE_PROJECTS,
        self::SCOPE_TESTERS,
        self::SCOPE_TEST_PLAN,
        self::SCOPE_QUESTIONS,
        self::SCOPE_ANSWERS,
    ];

    public const TESTER_SCOPES = [
        self::SCOPE_TEST_PLAN,
        self::SCOPE_QUESTIONS,
        self::SCOPE_ANSWERS,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BM25Scorer             $bm25,
    )
    {
    }

    /**
     * @param string[] $scopes
     * @return array<int, array{type: string, iri: string, score: float, name: string, extracts: string[]}>
     */
    public function search(string $query, array $scopes, ?User $user): array
    {
        if (trim($query) === '') {
            return [];
        }

        $isTester = $user instanceof User && $user->getType() === UserType::TESTER;

        if ($isTester) {
            $scopes = array_values(array_intersect($scopes, self::TESTER_SCOPES));
        }

        $terms = array_values(array_filter(
            array_map(trim(...), preg_split('/\s+/', mb_strtolower($query))),
            fn(string $t): bool => $t !== ''
        ));

        $results = [];

        foreach ($scopes as $scope) {
            $rows = match ($scope) {
                self::SCOPE_PROJECTS => $this->searchProjects($terms),
                self::SCOPE_TESTERS => $this->searchTesters($terms),
                self::SCOPE_TEST_PLAN => $this->searchTestPlans($terms, $isTester ? $user : null),
                self::SCOPE_QUESTIONS => $this->searchQuestions($terms, $isTester ? $user : null),
                self::SCOPE_ANSWERS => $this->searchAnswers($terms, $isTester ? $user : null),
                default => [],
            };

            foreach ($rows as $row) {
                if ($row['score'] > 0.0) {
                    $results[] = $row;
                }
            }
        }

        usort($results, fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /** @param string[] $terms */
    private function searchProjects(array $terms): array
    {
        $items = $this->fetchLike(
            'SELECT p FROM App\Entity\Project p WHERE LOWER(p.name) LIKE :p OR LOWER(p.description) LIKE :p',
            $terms
        );

        $documents = [];
        foreach ($items as $p) {
            /** @var Project $p */
            $documents[$p->getId()] = [
                'name' => ['text' => $p->getName() ?? '', 'weight' => 3.0],
                'description' => ['text' => $p->getDescription() ?? '', 'weight' => 1.0],
            ];
        }

        $scores = $this->bm25->score($documents, $terms);

        return array_map(function (Project $p) use ($scores): array {
            $s = $scores[$p->getId()] ?? ['score' => 0.0, 'extracts' => []];
            return [
                'type' => self::SCOPE_PROJECTS,
                'iri' => '/api/projects/' . $p->getId(),
                'score' => $s['score'],
                'name' => $p->getName() ?? '',
                'extracts' => $s['extracts'],
            ];
        }, $items);
    }

    /**
     * Run a DQL query with a LIKE :p parameter (and optional :user tester).
     * The LIKE pattern covers any document containing all query terms in order.
     *
     * @param string[] $terms
     */
    private function fetchLike(string $dql, array $terms, ?User $tester = null): array
    {
        $pattern = '%' . implode('%', $terms) . '%';
        $q = $this->em->createQuery($dql)->setParameter('p', $pattern);
        if ($tester instanceof User) {
            $q->setParameter('user', $tester);
        }
        return $q->getResult();
    }

    /** @param string[] $terms */
    private function searchTesters(array $terms): array
    {
        $items = $this->fetchLike(
            "SELECT u FROM App\Entity\User u WHERE u.type = 'TESTER' AND LOWER(u.email) LIKE :p",
            $terms
        );

        $documents = [];
        foreach ($items as $u) {
            /** @var User $u */
            $documents[(string)$u->getId()] = [
                'email' => ['text' => $u->getEmail() ?? '', 'weight' => 3.0],
            ];
        }

        $scores = $this->bm25->score($documents, $terms);

        return array_map(function (User $u) use ($scores): array {
            $s = $scores[(string)$u->getId()] ?? ['score' => 0.0, 'extracts' => []];
            return [
                'type' => self::SCOPE_TESTERS,
                'iri' => '/api/testers/' . $u->getId(),
                'score' => $s['score'],
                'name' => $u->getEmail() ?? '',
                'extracts' => $s['extracts'],
            ];
        }, $items);
    }

    /** @param string[] $terms */
    private function searchTestPlans(array $terms, ?User $tester): array
    {
        $dql = 'SELECT tp FROM App\Entity\TestPlan tp';
        $dql .= $tester instanceof User
            ? ' JOIN tp.testersEnrolled te WHERE te = :user AND (LOWER(tp.name) LIKE :p OR LOWER(tp.description) LIKE :p OR LOWER(tp.content) LIKE :p)'
            : ' WHERE LOWER(tp.name) LIKE :p OR LOWER(tp.description) LIKE :p OR LOWER(tp.content) LIKE :p';

        $items = $this->fetchLike($dql, $terms, $tester);

        $documents = [];
        foreach ($items as $tp) {
            /** @var TestPlan $tp */
            $documents[$tp->getId()] = [
                'name' => ['text' => $tp->getName() ?? '', 'weight' => 3.0],
                'description' => ['text' => $tp->getDescription() ?? '', 'weight' => 1.0],
                'content' => ['text' => $tp->getContent() ?? '', 'weight' => 1.0],
            ];
        }

        $scores = $this->bm25->score($documents, $terms);

        return array_map(function (TestPlan $tp) use ($scores): array {
            $s = $scores[$tp->getId()] ?? ['score' => 0.0, 'extracts' => []];
            return [
                'type' => self::SCOPE_TEST_PLAN,
                'iri' => '/api/test_plans/' . $tp->getId(),
                'score' => $s['score'],
                'name' => $tp->getName() ?? '',
                'extracts' => $s['extracts'],
            ];
        }, $items);
    }

    /** @param string[] $terms */
    private function searchQuestions(array $terms, ?User $tester): array
    {
        $dql = 'SELECT q FROM App\Entity\Question q';
        $dql .= $tester instanceof User
            ? ' JOIN q.plan tp JOIN tp.testersEnrolled te WHERE te = :user AND (LOWER(q.name) LIKE :p OR LOWER(q.content) LIKE :p)'
            : ' WHERE LOWER(q.name) LIKE :p OR LOWER(q.content) LIKE :p';

        $items = $this->fetchLike($dql, $terms, $tester);

        $documents = [];
        foreach ($items as $q) {
            /** @var Question $q */
            $documents[$q->getId()] = [
                'name' => ['text' => $q->getName() ?? '', 'weight' => 3.0],
                'content' => ['text' => $q->getContent() ?? '', 'weight' => 1.0],
            ];
        }

        $scores = $this->bm25->score($documents, $terms);

        return array_map(function (Question $q) use ($scores): array {
            $s = $scores[$q->getId()] ?? ['score' => 0.0, 'extracts' => []];
            return [
                'type' => self::SCOPE_QUESTIONS,
                'iri' => '/api/questions/' . $q->getId(),
                'score' => $s['score'],
                'name' => $q->getName() ?? '',
                'extracts' => $s['extracts'],
            ];
        }, $items);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /** @param string[] $terms */
    private function searchAnswers(array $terms, ?User $tester): array
    {
        $dql = 'SELECT a FROM App\Entity\Answer a WHERE LOWER(a.comment) LIKE :p';
        if ($tester instanceof User) {
            $dql .= ' AND a.tester = :user';
        }

        $items = $this->fetchLike($dql, $terms, $tester);

        $documents = [];
        foreach ($items as $a) {
            /** @var Answer $a */
            $documents[$a->getId()] = [
                'comment' => ['text' => $a->getComment() ?? '', 'weight' => 1.0],
            ];
        }

        $scores = $this->bm25->score($documents, $terms);

        return array_map(function (Answer $a) use ($scores): array {
            $s = $scores[$a->getId()] ?? ['score' => 0.0, 'extracts' => []];
            $comment = $a->getComment() ?? '';
            return [
                'type' => self::SCOPE_ANSWERS,
                'iri' => '/api/answers/' . $a->getId(),
                'score' => $s['score'],
                'name' => mb_strlen($comment) > 80 ? mb_substr($comment, 0, 80) . '…' : $comment,
                'extracts' => $s['extracts'],
            ];
        }, $items);
    }
}
