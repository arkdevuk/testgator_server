<?php

namespace App\State;


use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Me;
use App\ApiResource\ReleaseStats;
use App\Entity\Release;
use App\Entity\User;
use App\Services\Entities\ReleaseManager;
use Symfony\Bundle\SecurityBundle\Security;


class MeStateProvider implements ProviderInterface
{

    protected Security $security;

    public function __construct(
        Security $security,
    )
    {
        $this->security = $security;
    }

    public function provide(Operation $operation,
                            array     $uriVariables = [],
                            array     $context = []): object|array|null
    {
        if ($operation instanceof Get) {
            $u = $this->security->getUser();
            if (!$u instanceof User) {
                return null;
            }
            $me = new Me($u->getId());
            $me
                ->setUser($u)
                ->setEmail($u->getEmail())
                ->setRoles($u->getRoles())
                ->setType($u->getType()->value)
                ->setNickname($u->getNickname())
                ->setProfilePictureUrl($u->getProfilePictureUrl());
            return $me;
        }
        return null;
    }
}
