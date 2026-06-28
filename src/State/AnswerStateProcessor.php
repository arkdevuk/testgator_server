<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Answer;
use App\Entity\User;
use App\Event\AnswerUpdatedAppEvent;
use App\Event\NewAnswerAppEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * When a TESTER posts/puts/patches an answer:
 *   - answer.tester is always forced to the currently logged-in user
 *   - answer.important is always restored to its previous value (testers cannot change it)
 * Team users (type USER) can assign any tester and set important freely.
 */
final class AnswerStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface       $persistProcessor,
        private readonly Security                 $security,
        private readonly EventDispatcherInterface $dispatcher,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if ($data instanceof Answer) {
            $previous = $context['previous_data'] ?? null;

            if ($user instanceof User && $user->isTester()) {
                // Force tester to current user
                $data->setTester($user);

                // Restore important to its pre-request value; testers cannot change it
                if ($previous instanceof Answer) {
                    $data->setImportant($previous->isImportant());
                } else {
                    $data->setImportant(false);
                }
            }

            // `ignored` can only be set on an existing answer by ROLE_USER (dev team).
            // On POST (no previous data) always reset to false.
            // On PATCH/PUT by a tester, restore the previous value.
            if (!($previous instanceof Answer)) {
                $data->setIgnored(false);
            } elseif ($user instanceof User && $user->isTester()) {
                $data->setIgnored($previous->isIgnored());
            }
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Answer) {
            $event = $operation instanceof Post
                ? new NewAnswerAppEvent($result)
                : new AnswerUpdatedAppEvent($result);

            $this->dispatcher->dispatch($event);
        }

        return $result;
    }
}
