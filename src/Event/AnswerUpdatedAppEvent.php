<?php

namespace App\Event;

use App\Entity\Answer;
use Symfony\Contracts\EventDispatcher\Event;

final class AnswerUpdatedAppEvent extends Event
{
    public function __construct(
        public readonly Answer $answer,
    )
    {
    }
}
