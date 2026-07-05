<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Event\NewProjectAppEvent;
use App\Event\ProjectUpdatedAppEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ProjectStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface       $persistProcessor,
        private EventDispatcherInterface $dispatcher,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Project) {
            $event = $operation instanceof Post
                ? new NewProjectAppEvent($result)
                : new ProjectUpdatedAppEvent($result);

            $this->dispatcher->dispatch($event);
        }

        return $result;
    }
}
