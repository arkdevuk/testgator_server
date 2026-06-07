<?php

namespace App\Controller;

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

        return $this->json($answerRepository->getStatsByQuestion($id));
    }
}
