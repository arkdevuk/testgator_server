<?php

namespace App\EventListener;

use App\Entity\Tester;
use App\Services\Entities\TesterManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Tester::class)]
class CreateTesterListener
{
    protected EntityManagerInterface $entityManager;
    protected TesterManager $testerManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        TesterManager          $testerManager
    )
    {
        $this->entityManager = $entityManager;
        $this->testerManager = $testerManager;
    }

    public function postPersist(Tester $entity, PostPersistEventArgs $args): void
    {
        $this->testerManager->handlePostCreation($entity);
    }

}
