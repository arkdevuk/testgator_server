<?php

declare(strict_types=1);

namespace App\Enum;

enum AnswerState: string
{
    case PASS = 'pass';
    case PASS_WITH_BUGS = 'pass_with_bugs';
    case FAILED = 'failed';
    case BLOCKED = 'blocked';
    case PENDING = 'pending';
}
