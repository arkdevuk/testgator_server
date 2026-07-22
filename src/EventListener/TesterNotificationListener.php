<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\TesterAssignedAppEvent;
use App\Event\TestPlanClosedAppEvent;
use App\Event\TestPlanPublishedAppEvent;
use App\Services\Entities\TesterManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Emails enrolled testers about test plan lifecycle changes:
 *  - TestPlanPublishedAppEvent → "a plan you're on just published"
 *    (sent to every tester currently enrolled in the plan)
 *  - TestPlanClosedAppEvent    → "a plan you're on was closed"
 *    (sent to every tester currently enrolled in the plan)
 *  - TesterAssignedAppEvent    → "you've been invited to test"
 *    (sent to a single tester just enrolled into an already-published plan)
 */
#[AsEventListener(event: TestPlanPublishedAppEvent::class)]
#[AsEventListener(event: TestPlanClosedAppEvent::class)]
#[AsEventListener(event: TesterAssignedAppEvent::class)]
final readonly class TesterNotificationListener
{
    public function __construct(
        private TesterManager $testerManager,
    ) {
    }

    public function __invoke(TestPlanPublishedAppEvent|TestPlanClosedAppEvent|TesterAssignedAppEvent $event): void
    {
        match (true) {
            $event instanceof TestPlanPublishedAppEvent => $this->testerManager->notifyTestPlanPublished($event->testPlan),
            $event instanceof TestPlanClosedAppEvent => $this->testerManager->notifyTestPlanClosed($event->testPlan),
            $event instanceof TesterAssignedAppEvent => $this->testerManager->notifyTesterAssigned($event->testPlan, $event->tester),
        };
    }
}
