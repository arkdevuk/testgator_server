<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\QueryBuilder;

/**
 * The User entity is exposed through the API only as the "Tester" resource
 * (/api/testers). This extension restricts every API query on User to
 * type = TESTER so team accounts are never listed or fetched through it.
 */
final class TesterTypeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if ($resourceClass !== User::class) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('userType');

        $queryBuilder
            ->andWhere(sprintf('%s.type = :%s', $rootAlias, $parameter))
            ->setParameter($parameter, UserType::TESTER);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }
}
