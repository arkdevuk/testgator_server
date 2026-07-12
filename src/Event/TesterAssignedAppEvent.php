<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\TestPlan;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class TesterAssignedAppEvent extends Event
{
    public function __construct(
        public readonly TestPlan $testPlan,
        public readonly User $tester,
    ) {
    }
}
