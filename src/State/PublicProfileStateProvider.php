<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PublicProfile;
use App\Entity\User;
use App\Repository\UserRepository;

class PublicProfileStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof Get) {
            return null;
        }

        $id = $uriVariables['id'] ?? null;
        if ($id === null) {
            return null;
        }

        // Looks up any User regardless of type (both team members and testers).
        $user = $this->userRepository->find($id);
        if (!$user instanceof User) {
            return null;
        }

        return new PublicProfile()
            ->setId($user->getId())
            ->setType($user->getType()->value)
            ->setNickname($user->getNickname())
            ->setProfilePictureUrl($user->getProfilePictureUrl())
            ->setRoles($user->getRoles())
            ->setTags($user->getTags());
    }
}
