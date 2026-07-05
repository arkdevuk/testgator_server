<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use App\Event\NewQuestionAppEvent;
use App\Event\QuestionUpdatedAppEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class QuestionStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EventDispatcherInterface $dispatcher,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Question) {
            $event = $operation instanceof Post
                ? new NewQuestionAppEvent($result)
                : new QuestionUpdatedAppEvent($result);

            $this->dispatcher->dispatch($event);
        }

        return $result;
    }
}
