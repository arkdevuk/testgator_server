<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ProjectStats;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectStatsStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProjectRepository      $projectRepository,
        private readonly EntityManagerInterface $em,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $project = $this->projectRepository->find($uriVariables['id']);
        if (!$project instanceof Project) {
            throw new NotFoundHttpException();
        }

        $result = $this->em->createQuery(
            'SELECT
                COUNT(DISTINCT r.id)  AS releases,
                COUNT(DISTINCT tp.id) AS testPlans,
                COUNT(DISTINCT te.id) AS testers,
                COUNT(DISTINCT a.id)  AS answers
             FROM App\Entity\Project p
             LEFT JOIN p.releases        r
             LEFT JOIN r.plans           tp
             LEFT JOIN tp.testersEnrolled te
             LEFT JOIN tp.questions      q
             LEFT JOIN q.answers         a
             WHERE p.id = :id'
        )
            ->setParameter('id', $project->getId())
            ->getSingleResult();

        return new ProjectStats(
            releases: (int)$result['releases'],
            testPlans: (int)$result['testPlans'],
            testers: (int)$result['testers'],
            answers: (int)$result['answers'],
        );
    }
}
