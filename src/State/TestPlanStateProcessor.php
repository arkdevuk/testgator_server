<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Classes\TestPlanState;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Event\NewTestingPlanAppEvent;
use App\Event\TesterAssignedAppEvent;
use App\Event\TestingPlanUpdatedAppEvent;
use App\Event\TestPlanClosedAppEvent;
use App\Event\TestPlanPublishedAppEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class TestPlanStateProcessor implements ProcessorInterface
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
        $previous = $context['previous_data'] ?? null;

        // Capture the tester IDs present before the update so we can diff after persist.
        $previousTesterIds = $this->collectTesterIds($previous);

        // Capture previous state to detect a transition to published.
        $previousState = $previous instanceof TestPlan ? $previous->getState() : null;

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (!$result instanceof TestPlan) {
            return $result;
        }

        // Dispatch create / update event
        $this->dispatcher->dispatch(
            $operation instanceof Post
                ? new NewTestingPlanAppEvent($result)
                : new TestingPlanUpdatedAppEvent($result)
        );

        // Dispatch TestPlanPublishedAppEvent when state transitions to published.
        if (
            !$operation instanceof Post
            && $result->getState() === TestPlanState::PUBLISHED
            && $previousState !== TestPlanState::PUBLISHED
        ) {
            $this->dispatcher->dispatch(new TestPlanPublishedAppEvent($result));
        }

        // Dispatch TestPlanClosedAppEvent when state transitions to archived.
        if (
            !$operation instanceof Post
            && $result->getState() === TestPlanState::ARCHIVED
            && $previousState !== TestPlanState::ARCHIVED
        ) {
            $this->dispatcher->dispatch(new TestPlanClosedAppEvent($result));
        }

        // Dispatch TesterAssignedAppEvent for each newly added tester,
        // but only when the plan is published.
        if (
            !$operation instanceof Post
            && $result->getState() === TestPlanState::PUBLISHED
        ) {
            foreach ($result->getTestersEnrolled() as $tester) {
                /** @var User $tester */
                if (!in_array((string)$tester->getId(), $previousTesterIds, true)) {
                    $this->dispatcher->dispatch(new TesterAssignedAppEvent($result, $tester));
                }
            }
        }

        return $result;
    }

    /** @return string[] */
    private function collectTesterIds(mixed $previous): array
    {
        if (!$previous instanceof TestPlan) {
            return [];
        }

        return array_map(
            static fn(User $u): string => (string)$u->getId(),
            $previous->getTestersEnrolled()->toArray(),
        );
    }
}
