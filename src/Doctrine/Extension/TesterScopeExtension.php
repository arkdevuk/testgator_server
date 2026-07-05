<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Answer;
use App\Entity\Project;
use App\Entity\Settings;
use App\Entity\TestPlan;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts what non-admin accounts can see through the API.
 *
 * TESTER accounts:
 * - TestPlan:  only plans the tester is enrolled in
 * - Project:   only projects having at least one test plan the tester is enrolled in
 * - Answer:    only the tester's own answers
 *
 * All authenticated non-admin users (ROLE_USER and ROLE_TESTER):
 * - Settings:  only settings where public = true
 *   ROLE_ADMIN bypasses this filter and sees all settings.
 *
 * Applies to both collections and items (a non-matching item yields a 404).
 */
final readonly class TesterScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    )
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Settings visibility: ROLE_ADMIN sees everything; everyone else only
        // sees public=true rows (applies regardless of user type).
        if ($resourceClass === Settings::class) {
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                $queryBuilder->andWhere(sprintf('%s.public = true', $rootAlias));
            }
            return;
        }

        // All other restrictions apply to tester accounts only.
        $tester = $this->getCurrentTester();
        if (!$tester instanceof User) {
            return;
        }

        switch ($resourceClass) {
            case TestPlan::class:
                $parameter = $queryNameGenerator->generateParameterName('currentTester');
                $queryBuilder
                    ->andWhere(sprintf(':%s MEMBER OF %s.testersEnrolled', $parameter, $rootAlias))
                    ->setParameter($parameter, $tester);
                break;

            case Project::class:
                $parameter = $queryNameGenerator->generateParameterName('currentTester');
                $queryBuilder
                    ->andWhere(sprintf(
                        'EXISTS (SELECT tp_scope.id FROM %s tp_scope JOIN tp_scope.release r_scope WHERE r_scope.project = %s AND :%s MEMBER OF tp_scope.testersEnrolled)',
                        TestPlan::class,
                        $rootAlias,
                        $parameter
                    ))
                    ->setParameter($parameter, $tester);
                break;

            case Answer::class:
                $parameter = $queryNameGenerator->generateParameterName('currentTester');
                $queryBuilder
                    ->andWhere(sprintf('%s.tester = :%s', $rootAlias, $parameter))
                    ->setParameter($parameter, $tester);
                break;
        }
    }

    private function getCurrentTester(): ?User
    {
        $user = $this->security->getUser();
        if ($user instanceof User && $user->isTester()) {
            return $user;
        }

        return null;
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }
}
