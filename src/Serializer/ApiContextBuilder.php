<?php
// ApiContextBuilder
namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class ApiContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(private SerializerContextBuilderInterface $decorated, private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $currentMethod = $request->getMethod();
        $writeMethods = ['POST', 'PUT', 'PATCH'];

        if (isset($context['groups'])) {
            if ($this->authorizationChecker->isGranted('ROLE_SUPERADMIN') && in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'admin:write';
            }

            if ($this->authorizationChecker->isGranted('ROLE_SUPERADMIN') && !in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'admin:read';
            }

            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') && in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'team:write';
            }

            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') && !in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'team:read';
            }

            if ($this->authorizationChecker->isGranted('ROLE_TESTER') && in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'tester:write';
            }

            if ($this->authorizationChecker->isGranted('ROLE_TESTER') && !in_array($currentMethod, $writeMethods, true)) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $context['groups'][] = 'tester:read';
            }


        }

        return $context;
    }
}
