<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\PublicProfileStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ApiResource(
    normalizationContext: ['groups' => ['publicProfile:read']],
    provider: PublicProfileStateProvider::class,
)]
#[Get(
    uriTemplate: '/public-profiles/{id}',
    security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
)]
class PublicProfile
{
    #[Groups(['publicProfile:read'])]
    private ?Uuid $id = null;

    #[Groups(['publicProfile:read'])]
    private ?string $type = null;

    #[Groups(['publicProfile:read'])]
    private string $nickname = '';

    #[Groups(['publicProfile:read'])]
    private string $profilePictureUrl = '/assets/gator_avatar.png';

    #[Groups(['publicProfile:read'])]
    private array $roles = [];

    #[Groups(['publicProfile:read'])]
    private array $tags = [];

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    public function getProfilePictureUrl(): string
    {
        return $this->profilePictureUrl;
    }

    public function setProfilePictureUrl(string $profilePictureUrl): static
    {
        $this->profilePictureUrl = $profilePictureUrl;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
