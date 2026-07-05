<?php

namespace App\Classes\Users;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\UuidV7 as Uuid;

class AppGuest implements UserInterface
{
    private Uuid $uuid;

    public function __construct(private readonly int $tpId, ?string $uuid = null)
    {
        $this->uuid = $uuid !== null ? Uuid::fromString($uuid) : Uuid::v7();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', 'ROLE_GUEST'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->uuid?->toString() ?? '';
    }

    public function getTpId(): int
    {
        return $this->tpId;
    }
}
