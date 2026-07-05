<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Question;
use App\Entity\User;
use App\Services\QuestionStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionStatsController extends AbstractController
{
    public function __construct(private readonly QuestionStatsService $questionStatsService)
    {
    }

    #[Route('/api/questions/{id}/stats', name: 'app_question_stats', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $question = $this->questionStatsService->findQuestion($id);

        if (!$question instanceof Question) {
            return $this->json(['error' => 'Question not found'], 404);
        }

        // testers can only see stats of test plans they are enrolled in
        $user = $this->getUser();
        if ($user instanceof User && $user->isTester() && !$this->questionStatsService->testerCanAccess($question, $user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // JSON_PRESERVE_ZERO_FRACTION keeps answer_rate a float in the payload
        // (100.0 instead of 100), matching the documented response shape.
        return $this->json(
            $this->questionStatsService->getStats($id),
            context: ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION],
        );
    }
}
