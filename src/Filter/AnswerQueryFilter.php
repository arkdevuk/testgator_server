<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Answer;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter ?query=... — searches answer.comment and answer.systemInfos (cast to text).
 */
class AnswerQueryFilter extends AbstractFilter
{
    public function getDescription(string $resourceClass): array
    {
        return [
            'query' => [
                'property' => 'query',
                'type' => 'string',
                'required' => false,
                'swagger' => [
                    'description' => 'Full-text search across answer comment and systemInfos.',
                    'name' => 'query',
                    'type' => 'string',
                ],
            ],
        ];
    }

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void
    {
        if ($resourceClass !== Answer::class || $property !== 'query' || !is_string($value) || $value === '') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $param = $queryNameGenerator->generateParameterName('query');

        $queryBuilder
            ->andWhere(
                "LOWER({$alias}.comment) LIKE :{$param} OR LOWER(CAST_TEXT({$alias}.systemInfos)) LIKE :{$param}"
            )
            ->setParameter($param, '%' . strtolower($value) . '%');
    }
}
