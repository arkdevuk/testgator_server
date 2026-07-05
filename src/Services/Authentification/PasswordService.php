<?php

declare(strict_types=1);

namespace App\Services\Authentification;

use InvalidArgumentException;

/**
 * Validates plain-text passwords against the application's password policy.
 *
 * Rules:
 *  - At least 10 characters
 *  - At least 1 uppercase letter
 *  - At least 1 number
 *  - At least 1 symbol (any non-alphanumeric character)
 */
class PasswordService
{
    /**
     * @throws InvalidArgumentException with a human-readable message if any rule fails.
     */
    public function checkPassword(string $password): void
    {
        if (mb_strlen($password) < 10) {
            throw new InvalidArgumentException('Password must be at least 10 characters long.');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one uppercase letter.');
        }

        if (!preg_match('/\d/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one number.');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one symbol.');
        }
    }
}
