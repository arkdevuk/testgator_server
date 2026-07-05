<?php

declare(strict_types=1);

namespace App\Enum;

enum UserType: string
{
    case USER = 'USER';
    case TESTER = 'TESTER';
}
