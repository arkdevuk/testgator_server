<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Question;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;

class QuestionStatsService
{
    public function __construct(
        private readonly QuestionRepository $questionRepository,
        private readonly AnswerRepository $answerRepository,
    )
    {
    }

    /**
     * Fetch the question or return null if not found.
     */
    public function findQuestion(int $id): ?Question
    {
        return $this->questionRepository->find($id);
    }

    /**
     * Check whether a tester is allowed to view stats for the given question.
     */
    public function testerCanAccess(Question $question, User $user): bool
    {
        $plan = $question->getPlan();

        return $plan instanceof TestPlan && $plan->getTestersEnrolled()->contains($user);
    }

    /**
     * Return per-state counts and answer list for the given question ID.
     *
     * @return array{
     *   test_pass: int,
     *   test_pass_with_bugs: int,
     *   test_failed: int,
     *   test_blocked: int,
     *   test_pending: int,
     *   test_all_count: int,
     *   answer_rate: float,
     *   answers: list<array{answerId: int, date: string|null, state: string}>
     * }
     */
    public function getStats(int $questionId): array
    {
        return $this->answerRepository->getStatsByQuestion($questionId);
    }
}
