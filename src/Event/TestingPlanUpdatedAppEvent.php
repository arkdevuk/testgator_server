<?php

namespace App\Event;

use App\Entity\TestPlan;
use Symfony\Contracts\EventDispatcher\Event;

final class TestingPlanUpdatedAppEvent extends Event
{
    public function __construct(
        public readonly TestPlan $testPlan,
    )
    {
    }
}
