<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Services\Entities\TesterManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: User::class)]
class CreateTesterListener
{
    public function __construct(protected EntityManagerInterface $entityManager, protected TesterManager $testerManager)
    {
    }

    public function postPersist(User $entity, PostPersistEventArgs $args): void
    {
        // only send the welcome email to testers, not to team accounts
        if (!$entity->isTester()) {
            return;
        }

        $this->testerManager->handlePostCreation($entity);
    }

}
