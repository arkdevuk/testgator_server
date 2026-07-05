<?php

namespace App\Services\Entities;

use Exception;
use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserBuiltInDbService
{
    public function __construct(protected EntityManagerInterface $em, protected UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function createUser(array $userData, string $src = 'app'): User
    {
        $user = new User();

        $roles = ['ROLE_USER'];
        // if $userData['groups'] is not empty, and contains 'admin' role
        $key = $_ENV['LDAP_MUST_HAVE_GROUP'];
        if ($key === '') {
            $key = 'admin';
        }
        if (isset($userData['groups']) && in_array($key, $userData['groups'])) {
            $roles[] = 'ROLE_ADMIN';
        }

        $user
            ->setEmail($userData['email'])
            ->setType(UserType::USER)
            ->setRoles($roles)
            ->setPassword('')
            ->setSrc($src);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws Exception
     */
    public function checkUserLogin(string $email, string $password): ?User
    {
        $user = $this->getUserByEmail($email);
        if (!$user instanceof User) {
            throw new Exception('User not found');
        }

        $stored = $user->getPassword();
        if ($stored === null || $stored === '') {
            // Account exists but has no password set (e.g. LDAP-created account
            // or newly provisioned account awaiting admin password setup).
            throw new Exception('No password set for this account. Please contact an administrator.');
        }

        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            return $user;
        }

        throw new Exception('Invalid credentials');
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::USER]);
    }

    public function getUserByGuid(string $guid): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['id' => $guid, 'type' => UserType::USER]);
    }
}
