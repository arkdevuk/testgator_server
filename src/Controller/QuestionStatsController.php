<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionStatsController extends AbstractController
{
    #[Route('/api/questions/{id}/stats', name: 'app_question_stats', methods: ['GET'])]
    public function __invoke(
        int                $id,
        QuestionRepository $questionRepository,
        AnswerRepository   $answerRepository,
    ): Response
    {
        $question = $questionRepository->find($id);

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

        return $this->json($answerRepository->getStatsByQuestion($id));
    }
}
