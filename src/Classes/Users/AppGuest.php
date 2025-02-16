<?php

namespace App\Classes\Users;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\UuidV7 as Uuid;

class AppGuest implements UserInterface
{
    private int $tpId;

    private Uuid $uuid;

    public function __construct(int $tpId, ?string $uuid = null)
    {
        $this->tpId = $tpId;
        if ($uuid !== null) {
            $this->uuid = Uuid::fromString($uuid);
        } else {
            $this->uuid = Uuid::v7();
        }
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
