<?php

namespace App\Services\Entities;

use App\Entity\TestPlan;
use Doctrine\ORM\EntityManagerInterface;

class TestPlanManager
{
    protected EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->em = $em;
    }

    public function getTestPlanById(int $id): ?TestPlan
    {
        return $this->em->getRepository(TestPlan::class)
            ->findOneBy(['id' => $id]);
    }
}
