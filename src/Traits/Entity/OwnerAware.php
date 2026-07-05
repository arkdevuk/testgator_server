<?php

declare(strict_types=1);

namespace App\Traits\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait OwnerAware
{
    #[ORM\ManyToOne]
    #[Groups(['owner:read', 'user:minimal:read'])]
    public ?User $owner = null;

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
}
