<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\TestPlan;
use App\Entity\User;
use App\Event\TesterAssignedAppEvent;
use App\Event\TestPlanClosedAppEvent;
use App\Event\TestPlanPublishedAppEvent;
use App\EventListener\TesterNotificationListener;
use App\Services\Entities\TesterManager;
use PHPUnit\Framework\TestCase;

class TesterNotificationListenerTest extends TestCase
{
    public function testPublishedEventNotifiesTestPlanPublished(): void
    {
        $plan = $this->createStub(TestPlan::class);

        $testerManager = $this->createMock(TesterManager::class);
        $testerManager->expects(self::once())->method('notifyTestPlanPublished')->with($plan);
        $testerManager->expects(self::never())->method('notifyTestPlanClosed');
        $testerManager->expects(self::never())->method('notifyTesterAssigned');

        (new TesterNotificationListener($testerManager))(new TestPlanPublishedAppEvent($plan));
    }

    public function testClosedEventNotifiesTestPlanClosed(): void
    {
        $plan = $this->createStub(TestPlan::class);

        $testerManager = $this->createMock(TesterManager::class);
        $testerManager->expects(self::once())->method('notifyTestPlanClosed')->with($plan);
        $testerManager->expects(self::never())->method('notifyTestPlanPublished');
        $testerManager->expects(self::never())->method('notifyTesterAssigned');

        (new TesterNotificationListener($testerManager))(new TestPlanClosedAppEvent($plan));
    }

    public function testTesterAssignedEventNotifiesTesterAssigned(): void
    {
        $plan = $this->createStub(TestPlan::class);
        $tester = $this->createStub(User::class);

        $testerManager = $this->createMock(TesterManager::class);
        $testerManager->expects(self::once())->method('notifyTesterAssigned')->with($plan, $tester);
        $testerManager->expects(self::never())->method('notifyTestPlanPublished');
        $testerManager->expects(self::never())->method('notifyTestPlanClosed');

        (new TesterNotificationListener($testerManager))(new TesterAssignedAppEvent($plan, $tester));
    }
}
