<?php

namespace App\State;


use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ReleaseStats;
use App\Entity\Release;
use App\Services\Entities\ReleaseManager;


class ReleaseStatsStateProvider implements ProviderInterface
{

    public function __construct(protected ReleaseManager $releaseManager)
    {
    }

    public function provide(Operation $operation,
                            array     $uriVariables = [],
                            array     $context = []): object|array|null
    {
        if ($operation instanceof Get) {
            // todo add symfony/cache to avoid recomputing this data every time | ~10 minutes

            $releaseId = $uriVariables['id'] ?? null;
            if ($releaseId === null) {
                return null;
            }
            $release = $this->releaseManager->getId($releaseId);
            if (!$release instanceof Release) {
                return null;
            }
            $stats = new ReleaseStats($releaseId);
            $stats->setRelease($release);

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

            $stats->setTotalQuestions($totalQuestions);
            $stats->setTotalWait($totalWait);
            $stats->setTotalResponded($totalResponded);
            $stats->setTotalPlans($totalPlans);

            return $stats;
        }
        return null;
    }
}
