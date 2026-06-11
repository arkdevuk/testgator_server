<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;


// filter will be like /api/testers?testingPlan=12
// we will filter testers based on the testing plan id so we can use other filters from the testers entity
class TesterTestingPlanFilter extends AbstractFilter
{

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        // filter only if the User entity (exposed as the Tester resource) is being queried
        if ($resourceClass !== User::class) {
            return;
        }

        // if the property is not the one we are looking for, return
        if ($property !== 'testingPlan') {
            return;
        }

        // if value start with /api/test_plans/ then extract the id
        if (str_starts_with($value, '/api/test_plans/')) {
            $value = (int)str_replace('/api/test_plans/', '', $value);
        }

        // add the where clause to the query,
        // there is no property in the Tester entity that references the TestPlan entity
        // in TestPlan #[ORM\ManyToMany(targetEntity: Tester::class)] private Collection $testersEnrolled;
        // so we need to join the TestPlan entity
        // and then filter the testers based on the testing plan id

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('testingPlan');
        $queryBuilder
            ->join($rootAlias . '.projects', 'projects')
            ->join('projects.releases', 'releases')
            ->join('releases.plans', 'plans')
            ->andWhere('plans.id = :' . $parameter)
            ->setParameter($parameter, $value);


    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'testing-plan' => [
                'property' => 'testingPlan',
                'type' => 'string',
                'required' => false,
                'swagger' => [
                    'description' => 'Filter testers based on the testing plan id. You can use the testing plan id or the testing plan IRI',
                    'name' => 'testing-plan',
                    'type' => 'string',
                ],
            ],
        ];
    }
}
