<?php

declare(strict_types=1);

namespace App\Services\Authentification;

use App\Entity\User;
use App\Event\UserPasswordChangedAppEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Validates and changes passwords according to the application's password policy.
 *
 * Rules:
 *  - At least 10 characters
 *  - At least 1 uppercase letter
 *  - At least 1 number
 *  - At least 1 symbol (any non-alphanumeric character)
 */
class PasswordService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserRepository $userRepository,
    )
    {
    }

    /**
     * Validate, hash, persist, and dispatch the password-changed event.
     *
     * @throws InvalidArgumentException if the password fails policy checks
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $this->checkPassword($newPassword);
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $this->em->flush();
        $this->dispatcher->dispatch(new UserPasswordChangedAppEvent($user));
    }

    /**
     * Find a user by ID, then change their password.
     * Returns the User on success, or null if not found.
     *
     * @throws InvalidArgumentException if the password fails policy checks
     */
    public function changePasswordById(string $id, string $newPassword): ?User
    {
        $user = $this->userRepository->find($id);
        if (!$user instanceof User) {
            return null;
        }

        $this->changePassword($user, $newPassword);

        return $user;
    }

    /**
     * @throws InvalidArgumentException with a human-readable message if any rule fails
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
