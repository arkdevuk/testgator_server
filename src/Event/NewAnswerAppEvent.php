<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Answer;
use Symfony\Contracts\EventDispatcher\Event;

final class NewAnswerAppEvent extends Event
{
    public function __construct(
        public readonly Answer $answer,
    )
    {
    }
}
