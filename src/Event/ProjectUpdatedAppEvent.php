<?php

namespace App\Event;

use App\Entity\Project;
use Symfony\Contracts\EventDispatcher\Event;

final class ProjectUpdatedAppEvent extends Event
{
    public function __construct(
        public readonly Project $project,
    )
    {
    }
}
