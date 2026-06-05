<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** Purge tokens that have been expired or revoked for more than $days days */
    public function purgeStale(int $days = 7): int
    {
        $cutoff = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :cutoff OR rt.revokedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
