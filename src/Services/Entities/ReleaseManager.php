<?php

namespace App\Services\Entities;

use App\Entity\Release;
use Doctrine\ORM\EntityManagerInterface;

class ReleaseManager
{
    protected EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->em = $em;
    }

    public function getId(int $id): ?Release
    {
        return $this->em->getRepository(Release::class)
            ->findOneBy(['id' => $id]);
    }
}
