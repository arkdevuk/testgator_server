<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Index(name: 'idx_refresh_token_hash', columns: ['token_hash'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** SHA-256 of the raw token — never store the raw value */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    /** UUID of the User or Tester this token belongs to */
    #[ORM\Column(length: 36)]
    private string $userGuid;

    /** 'user', 'tester', or 'guest' */
    #[ORM\Column(length: 10)]
    private string $userType;

    /** Arbitrary extra context, e.g. ['tp_id' => 42] for guest tokens */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extra = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        string $tokenHash,
        string $userGuid,
        string $userType,
        DateTimeImmutable $expiresAt,
        ?array $extra = null,
    ) {
        $this->tokenHash = $tokenHash;
        $this->userGuid = $userGuid;
        $this->userType = $userType;
        $this->expiresAt = $expiresAt;
        $this->extra = $extra;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getUserGuid(): string
    {
        return $this->userGuid;
    }

    public function getUserType(): string
    {
        return $this->userType;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isValid(): bool
    {
        return !$this->revokedAt instanceof DateTimeImmutable
            && $this->expiresAt > new DateTimeImmutable();
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }
}
