<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
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
use App\State\TestPlanStateProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for the tester-notification event dispatch matrix in
 * TestPlanStateProcessor. These events drive the "plan published" /
 * "plan closed" / "you've been invited" emails, so the exact conditions
 * under which each one fires matter:
 *
 *  - TestPlanPublishedAppEvent: any transition INTO published (from draft
 *    or archived). The listener mails every tester currently enrolled,
 *    which is what covers testers enrolled before the plan went live.
 *  - TestPlanClosedAppEvent: any transition OUT of published (to draft or
 *    archived) — not just "to archived".
 *  - TesterAssignedAppEvent: only for testers newly enrolled into a plan
 *    that was ALREADY published (previous state === published, new state
 *    === published). Testers added as part of the publish transition
 *    itself must NOT get this — they get the "published" mail instead.
 */
class TestPlanStateProcessorTest extends TestCase
{
    public function testDraftToPublishedDispatchesPublishedButNotClosedOrAssigned(): void
    {
        $testerA = $this->makeTester('a');

        $previous = $this->makePlan(TestPlanState::DRAFT, [$testerA]);
        $new = $this->makePlan(TestPlanState::PUBLISHED, [$testerA]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventDispatched($dispatched, TestingPlanUpdatedAppEvent::class);
        self::assertEventDispatched($dispatched, TestPlanPublishedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestPlanClosedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TesterAssignedAppEvent::class);
    }

    public function testDraftToPublishedWithNewTesterOnlyDispatchesPublished(): void
    {
        // Tester B is added in the very same request that publishes the plan.
        // They must get the "published" mail (via the loop over all enrolled
        // testers in the listener), NOT a separate "invited" mail.
        $testerA = $this->makeTester('a');
        $testerB = $this->makeTester('b');

        $previous = $this->makePlan(TestPlanState::DRAFT, [$testerA]);
        $new = $this->makePlan(TestPlanState::PUBLISHED, [$testerA, $testerB]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventDispatched($dispatched, TestPlanPublishedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TesterAssignedAppEvent::class);
    }

    public function testNewTesterAddedToAlreadyPublishedPlanDispatchesAssignedOnly(): void
    {
        $testerA = $this->makeTester('a');
        $testerB = $this->makeTester('b');

        $previous = $this->makePlan(TestPlanState::PUBLISHED, [$testerA]);
        $new = $this->makePlan(TestPlanState::PUBLISHED, [$testerA, $testerB]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventNotDispatched($dispatched, TestPlanPublishedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestPlanClosedAppEvent::class);

        $assigned = array_values(array_filter($dispatched, static fn ($e) => $e instanceof TesterAssignedAppEvent));
        self::assertCount(1, $assigned);
        self::assertSame($testerB, $assigned[0]->tester);
    }

    public function testPublishedToArchivedDispatchesClosed(): void
    {
        $testerA = $this->makeTester('a');

        $previous = $this->makePlan(TestPlanState::PUBLISHED, [$testerA]);
        $new = $this->makePlan(TestPlanState::ARCHIVED, [$testerA]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventDispatched($dispatched, TestPlanClosedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestPlanPublishedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TesterAssignedAppEvent::class);
    }

    public function testPublishedToDraftAlsoDispatchesClosed(): void
    {
        // "Closed" means leaving the published state at all, not just
        // archiving — sending a plan back to draft counts too.
        $testerA = $this->makeTester('a');

        $previous = $this->makePlan(TestPlanState::PUBLISHED, [$testerA]);
        $new = $this->makePlan(TestPlanState::DRAFT, [$testerA]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventDispatched($dispatched, TestPlanClosedAppEvent::class);
    }

    public function testDraftToArchivedDoesNotDispatchClosed(): void
    {
        // The plan was never live, so testers were never notified it
        // published — there's nothing to tell them was "closed" either.
        $testerA = $this->makeTester('a');

        $previous = $this->makePlan(TestPlanState::DRAFT, [$testerA]);
        $new = $this->makePlan(TestPlanState::ARCHIVED, [$testerA]);

        $dispatched = $this->process($new, $previous, new Patch());

        self::assertEventNotDispatched($dispatched, TestPlanClosedAppEvent::class);
    }

    public function testCreateOperationOnlyDispatchesNewTestingPlanEvent(): void
    {
        $new = $this->makePlan(TestPlanState::PUBLISHED, [$this->makeTester('a')]);

        $dispatched = $this->process($new, null, new Post());

        self::assertEventDispatched($dispatched, NewTestingPlanAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestingPlanUpdatedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestPlanPublishedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TestPlanClosedAppEvent::class);
        self::assertEventNotDispatched($dispatched, TesterAssignedAppEvent::class);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /** @return list<object> */
    private function process(TestPlan $new, ?TestPlan $previous, Operation $operation): array
    {
        $persistProcessor = $this->createStub(ProcessorInterface::class);
        $persistProcessor->method('process')->willReturn($new);

        $dispatched = [];
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched) {
            $dispatched[] = $event;

            return $event;
        });

        $processor = new TestPlanStateProcessor($persistProcessor, $dispatcher);
        $processor->process($new, $operation, [], ['previous_data' => $previous]);

        return $dispatched;
    }

    private function makePlan(string $state, array $testers): TestPlan
    {
        $plan = new TestPlan();
        $plan->setState($state);
        foreach ($testers as $tester) {
            $plan->addTestersEnrolled($tester);
        }

        return $plan;
    }

    /**
     * $seed is unused beyond documenting intent at call sites — object
     * identity (not a matching seed) is what makes two references
     * represent "the same tester" across previous/new plan snapshots,
     * matching how Doctrine would hand back the same User instance.
     */
    private function makeTester(string $seed): User
    {
        $tester = $this->createStub(User::class);
        $tester->method('getId')->willReturn(Uuid::v7());

        return $tester;
    }

    /** @param list<object> $dispatched */
    private static function assertEventDispatched(array $dispatched, string $eventClass): void
    {
        $matches = array_filter($dispatched, static fn ($e) => $e instanceof $eventClass);
        self::assertNotEmpty($matches, sprintf('Expected %s to be dispatched, but it was not.', $eventClass));
    }

    /** @param list<object> $dispatched */
    private static function assertEventNotDispatched(array $dispatched, string $eventClass): void
    {
        $matches = array_filter($dispatched, static fn ($e) => $e instanceof $eventClass);
        self::assertEmpty($matches, sprintf('Expected %s NOT to be dispatched, but it was.', $eventClass));
    }
}
