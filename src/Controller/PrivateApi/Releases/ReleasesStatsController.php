<?php

namespace App\Controller\PrivateApi\Releases;

use App\Entity\Release;
use App\Services\Entities\ReleaseManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReleasesStatsController extends AbstractController
{
    #[Route('/apx/stats/releases/{id}', name: 'stats_releases')]
    public function statsReleases(
        int            $id,
        ReleaseManager $releaseManager
    ): Response
    {
        // todo add symfony/cache to avoid recomputing this data every time | ~10 minutes

        $release = $releaseManager->getId($id);
        if (!$release instanceof Release) {
            return $this->json([
                'error' => 'Release not found'
            ], 404);
        }

        $totalPlans = 0;
        $totalQuestions = 0;
        $totalWait = 0;
        $totalResponded = 0;
        $totalPlans = 0;

        foreach ($release->getPlans()->getIterator() as $plan) {
            $totalPlans++;
            $totalQuestions += $q = $plan->getQuestions()->count();
            $testers = $plan->getTestersEnrolled()->count();
            $totalWait += ($q * $testers);
            $r = 0;
            foreach ($plan->getQuestions()->getIterator() as $question) {
                $r += $question->getAnswers()->count();
            }
            $totalResponded += $r;
        }

        $percentage = 0;
        if ($totalWait !== 0 && $totalResponded !== 0) {
            $percentage = ($totalResponded / $totalWait) * 100;
        }

        return $this->json([
            'id' => $id,
            'plans' => $totalPlans,
            'questions' => $totalQuestions,
            'wait' => $totalWait,
            'responded' => $totalResponded,
            'percentage_done' => $percentage,
        ]);
    }


}
