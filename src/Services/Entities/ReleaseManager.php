<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Entity\Release;
use Doctrine\ORM\EntityManagerInterface;

class ReleaseManager
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function getId(int $id): ?Release
    {
        return $this->em->getRepository(Release::class)
            ->findOneBy(['id' => $id]);
    }
}
