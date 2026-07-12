<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\TestPlan;
use Symfony\Contracts\EventDispatcher\Event;

final class NewTestingPlanAppEvent extends Event
{
    public function __construct(
        public readonly TestPlan $testPlan,
    ) {
    }
}
