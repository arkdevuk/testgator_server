<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Question;
use Symfony\Contracts\EventDispatcher\Event;

final class NewQuestionAppEvent extends Event
{
    public function __construct(
        public readonly Question $question,
    ) {
    }
}
