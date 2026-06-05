<?php

namespace App\Services\Entities;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserBuiltInDbService
{
    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
    )
    {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @param array $userData
     * @return void
     */
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
            ->setRoles($roles)
            ->setPassword('')
            ->setSrc($src);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws \Exception
     */
    public function checkUserLogin(string $email, string $password): ?User
    {
        $user = $this->getUserByEmail($email);
        if ($user === null) {
            throw new \Exception('User not found');
        }

        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            return $user;
        }

        throw new \Exception('Invalid credentials');
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function getUserByGuid(string $guid): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['id' => $guid]);
    }
}
