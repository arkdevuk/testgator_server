<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\MeStateProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ApiResource(
    normalizationContext: ['groups' => ['userSelf:read']],
    denormalizationContext: ['groups' => ['userSelf:write']],
    provider: MeStateProvider::class
)]
#[Get(uriTemplate: '/auth/me', security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')")]
class Me
{
    #[Groups(['none:none'])]
    private UserInterface $user;

    #[ApiProperty(identifier: false)]
    #[Groups(['userSelf:read'])]
    private ?Uuid $id = null;

    #[Groups(['userSelf:read'])]
    private ?string $email = null;

    #[Groups(['userSelf:read'])]
    private array $roles = [];

    /**
     * Account type: USER (team member) or TESTER
     */
    #[Groups(['userSelf:read'])]
    private ?string $type = null;

    public function __construct(?Uuid $userId = null)
    {
        $this->id = $userId;
    }


    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): Me
    {
        $this->user = $user;
        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): Me
    {
        $this->email = $email;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): Me
    {
        $this->roles = $roles;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): Me
    {
        $this->type = $type;
        return $this;
    }


}
