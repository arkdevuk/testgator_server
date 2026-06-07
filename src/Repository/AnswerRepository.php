<?php

namespace App\Repository;

use App\Entity\Answer;
use App\Enum\AnswerState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Answer>
 */
class AnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    /**
     * Returns per-state counts and the answer list for a given question.
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
    public function getStatsByQuestion(int $questionId): array
    {
        /** @var Answer[] $answers */
        $answers = $this->createQueryBuilder('a')
            ->where('a.question = :qid')
            ->setParameter('qid', $questionId)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [
            AnswerState::PASS->value => 0,
            AnswerState::PASS_WITH_BUGS->value => 0,
            AnswerState::FAILED->value => 0,
            AnswerState::BLOCKED->value => 0,
            AnswerState::PENDING->value => 0,
        ];

        $answerList = [];

        foreach ($answers as $answer) {
            $state = $answer->getState()->value;
            $counts[$state] = ($counts[$state] ?? 0) + 1;

            $date = ($answer->getUpdated() ?? $answer->getCreated())?->format(\DateTimeInterface::ATOM);

            $answerList[] = [
                'answerId' => $answer->getId(),
                'date' => $date,
                'state' => $state,
            ];
        }

        $total = count($answers);
        $answered = $counts[AnswerState::PASS->value]
            + $counts[AnswerState::PASS_WITH_BUGS->value]
            + $counts[AnswerState::FAILED->value]
            + $counts[AnswerState::BLOCKED->value];

        $rate = $total > 0 ? round($answered / $total * 100, 2) : 0.0;

        return [
            'test_pass' => $counts[AnswerState::PASS->value],
            'test_pass_with_bugs' => $counts[AnswerState::PASS_WITH_BUGS->value],
            'test_failed' => $counts[AnswerState::FAILED->value],
            'test_blocked' => $counts[AnswerState::BLOCKED->value],
            'test_pending' => $counts[AnswerState::PENDING->value],
            'test_all_count' => $total,
            'answer_rate' => $rate,
            'answers' => $answerList,
        ];
    }
}
