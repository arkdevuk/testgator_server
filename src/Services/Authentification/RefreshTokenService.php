<?php

namespace App\Services\Authentification;

use App\Entity\RefreshToken;
use App\Entity\Tester;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RefreshTokenService
{
    /** Refresh tokens are valid for 30 days */
    private const TTL_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repo,
    )
    {
    }

    /**
     * Issue a new refresh token for the given user.
     * Pass $extra to store arbitrary context (e.g. ['tp_id' => 42] for guest tokens).
     * Returns the raw token (shown once — only the hash is persisted).
     */
    public function issue(UserInterface $user, string $userType, ?array $extra = null): string
    {
        $raw = $this->generateRaw();
        $hash = $this->hash($raw);

        $token = new RefreshToken(
            tokenHash: $hash,
            userGuid: $user->getId()?->toString() ?? '',
            userType: $userType,
            expiresAt: new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'),
            extra: $extra,
        );

        $this->em->persist($token);
        $this->em->flush();

        return $raw;
    }

    /**
     * Issue a guest refresh token with no UserInterface entity.
     * Used for login_tester where the session is scoped to a TestPlan, not a DB user.
     */
    public function issueGuest(string $challenge, array $extra): string
    {
        $raw = $this->generateRaw();
        $hash = $this->hash($raw);

        $token = new RefreshToken(
            tokenHash: $hash,
            userGuid: $challenge,
            userType: 'guest',
            expiresAt: new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'),
            extra: $extra,
        );

        $this->em->persist($token);
        $this->em->flush();

        return $raw;
    }

    /**
     * Validate a raw refresh token and rotate it:
     * the old token is revoked and a new one is issued in one transaction.
     *
     * Returns ['userGuid' => ..., 'userType' => ..., 'refreshToken' => <new raw>]
     * or throws \RuntimeException on any failure.
     *
     * @throws \RuntimeException
     */
    public function consume(string $rawToken): array
    {
        $record = $this->repo->findByHash($this->hash($rawToken));

        if ($record === null) {
            throw new \RuntimeException('Refresh token not found.');
        }

        if (!$record->isValid()) {
            // Revoke as a precaution in case it was already used (token reuse detection)
            if ($record->getRevokedAt() === null) {
                $record->revoke();
                $this->em->flush();
            }
            throw new \RuntimeException('Refresh token is expired or revoked.');
        }

        // Rotate: revoke old, issue new — both in one flush
        $record->revoke();

        $newRaw = $this->generateRaw();
        $newHash = $this->hash($newRaw);

        $newToken = new RefreshToken(
            tokenHash: $newHash,
            userGuid: $record->getUserGuid(),
            userType: $record->getUserType(),
            expiresAt: new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'),
            extra: $record->getExtra(),
        );

        $this->em->persist($newToken);
        $this->em->flush();

        return [
            'userGuid' => $record->getUserGuid(),
            'userType' => $record->getUserType(),
            'extra' => $record->getExtra(),
            'refreshToken' => $newRaw,
        ];
    }

    /**
     * Immediately revoke a token by its raw value.
     * Silently does nothing if the token is not found.
     */
    public function revoke(string $rawToken): void
    {
        $record = $this->repo->findByHash($this->hash($rawToken));
        if ($record !== null && $record->getRevokedAt() === null) {
            $record->revoke();
            $this->em->flush();
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function generateRaw(): string
    {
        return bin2hex(random_bytes(48)); // 96 hex chars = 384 bits of entropy
    }

    private function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
