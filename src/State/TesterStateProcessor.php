<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\UserType;
use App\Event\TesterCreatedAppEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Forces type = TESTER on every User created/updated through the
 * /api/testers resource, then delegates to the default Doctrine processors.
 */
final class TesterStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface       $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface       $removeProcessor,
        private readonly EventDispatcherInterface $dispatcher,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($data instanceof User) {
            $data->setType(UserType::TESTER);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($operation instanceof Post && $result instanceof User) {
            $this->dispatcher->dispatch(new TesterCreatedAppEvent($result));
        }

        return $result;
    }
}
