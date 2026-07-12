<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Project;
use Symfony\Contracts\EventDispatcher\Event;

final class NewProjectAppEvent extends Event
{
    public function __construct(
        public readonly Project $project,
    ) {
    }
}
