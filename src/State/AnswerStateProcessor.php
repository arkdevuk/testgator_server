<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Answer;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * When a TESTER posts an answer, answer.tester is always forced to the
 * currently logged-in user, ignoring whatever the payload contains.
 * Team users (type USER) can assign any tester.
 */
final class AnswerStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security           $security,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if ($data instanceof Answer && $user instanceof User && $user->isTester()) {
            $data->setTester($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
