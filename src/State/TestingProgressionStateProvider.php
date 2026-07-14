<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TestingProgression;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Enum\AnswerState;
use App\Repository\TestPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestingProgressionStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly TestPlanRepository $testPlanRepository,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $testPlan = $this->testPlanRepository->find($uriVariables['id']);
        if (!$testPlan instanceof TestPlan) {
            throw new NotFoundHttpException();
        }

        // Check the current user is enrolled in this test plan
        $enrolled = $testPlan->getTestersEnrolled()->exists(
            static fn (int $_, User $tester): bool => $tester->getId() === $user->getId()
        );
        if (!$enrolled) {
            throw new AccessDeniedHttpException();
        }

        // Fetch all answers by current user for questions in this plan in one query
        $answers = $this->em->createQuery(
            'SELECT a FROM App\Entity\Answer a
             JOIN a.question q
             WHERE q.plan = :plan
             AND a.tester = :user'
        )
            ->setParameter('plan', $testPlan)
            ->setParameter('user', $user)
            ->getResult();

        $totalQuestions = $testPlan->getTotalQuestions();

        // Index answers by question id (one answer per question for this tester)
        $answersByQuestion = [];
        foreach ($answers as $answer) {
            $qid = $answer->getQuestion()->getId();
            // Keep the latest if somehow duplicates exist
            $answersByQuestion[$qid] = $answer;
        }

        $questionsAnswered = 0;
        foreach ($answersByQuestion as $answer) {
            if ($answer->getState() !== AnswerState::PENDING) {
                ++$questionsAnswered;
            }
        }

        $questionsPending = $totalQuestions - $questionsAnswered;
        $progression = $totalQuestions > 0
            ? round($questionsAnswered / $totalQuestions * 100, 2)
            : 0.0;

        return new TestingProgression(
            questions: $totalQuestions,
            questionsAnswered: $questionsAnswered,
            questionsPending: $questionsPending,
            progression: $progression,
        );
    }
}
