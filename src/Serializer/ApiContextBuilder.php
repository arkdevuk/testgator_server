<?php
// ApiContextBuilder
namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ApiContextBuilder implements SerializerContextBuilderInterface
{
    private $decorated;
    private $authorizationChecker;

    public function __construct(SerializerContextBuilderInterface $decorated,
                                AuthorizationCheckerInterface     $authorizationChecker)
    {
        $this->decorated = $decorated;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        $resourceClass = $context['resource_class'] ?? null;

        if (isset($context['groups']) && $this->authorizationChecker->isGranted('ROLE_SUPERADMIN') && false === $normalization) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $context['groups'][] = 'admin:write';
        }

        if (isset($context['groups']) && $this->authorizationChecker->isGranted('ROLE_SUPERADMIN') && true === $normalization) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $context['groups'][] = 'admin:read';
        }

        if (isset($context['groups']) && $this->authorizationChecker->isGranted('ROLE_ADMIN') && false === $normalization) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $context['groups'][] = 'team:write';
        }

        if (isset($context['groups']) && $this->authorizationChecker->isGranted('ROLE_ADMIN') && true === $normalization) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $context['groups'][] = 'team:read';
        }

        return $context;
    }
}
