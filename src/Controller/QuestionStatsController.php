<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionStatsController extends AbstractController
{
    public function __construct(private readonly QuestionRepository $questionRepository, private readonly AnswerRepository $answerRepository)
    {
    }
    #[Route('/api/questions/{id}/stats', name: 'app_question_stats', methods: ['GET'])]
    public function __invoke(
        int                $id,
    ): JsonResponse
    {
        $question = $this->questionRepository->find($id);

        if ($question === null) {
            return $this->json(['error' => 'Question not found'], 404);
        }

        // testers can only see stats of test plans they are enrolled in
        $user = $this->getUser();
        if ($user instanceof User && $user->isTester()) {
            $plan = $question->getPlan();
            if ($plan === null || !$plan->getTestersEnrolled()->contains($user)) {
                return $this->json(['error' => 'Access denied'], 403);
            }
        }

        return $this->json($this->answerRepository->getStatsByQuestion($id));
    }
}
