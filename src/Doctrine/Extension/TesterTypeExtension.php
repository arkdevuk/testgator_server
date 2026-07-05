<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes User queries to the correct type depending on which API resource
 * triggered the query:
 *  - shortName "Tester" (/api/testers) → type = TESTER
 *  - shortName "User"   (/api/users)   → type = USER.
 *
 * Without this, both resources would share the same unfiltered query and
 * expose the wrong account type.
 */
final class TesterTypeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }

    private function addWhere(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation): void
    {
        if ($resourceClass !== User::class) {
            return;
        }

        $shortName = $operation?->getShortName();

        $type = match ($shortName) {
            'Tester' => UserType::TESTER,
            'User' => UserType::USER,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('userType');

        $queryBuilder
            ->andWhere(sprintf('%s.type = :%s', $rootAlias, $parameter))
            ->setParameter($parameter, $type);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }
}
