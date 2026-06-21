<?php

namespace App\Event;

use App\Entity\TestPlan;
use Symfony\Contracts\EventDispatcher\Event;

final class TestPlanClosedAppEvent extends Event
{
    public function __construct(
        public readonly TestPlan $testPlan,
    )
    {
    }
}
