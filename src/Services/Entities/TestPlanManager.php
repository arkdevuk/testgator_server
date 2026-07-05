<?php

namespace App\Services\Entities;

use App\Entity\TestPlan;
use Doctrine\ORM\EntityManagerInterface;

class TestPlanManager
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function getTestPlanById(int $id): ?TestPlan
    {
        return $this->em->getRepository(TestPlan::class)
            ->findOneBy(['id' => $id]);
    }
}
