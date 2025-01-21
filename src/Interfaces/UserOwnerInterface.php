<?php

namespace App\Interfaces;

use App\Entity\User;

interface UserOwnerInterface
{
    public function setOwner(?User $owner): self;

    public function getOwner(): ?User;
}
