<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Services\Authentification\JWTService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class UploadAuthenticator extends AbstractAuthenticator
{
    public function __construct(protected JWTService $JWTService, protected UserRepository $userRepository)
    {
    }

    public function supports(
        Request $request
    ): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $headerValue = $request->headers->get('Authorization');
        $jwtString = trim(str_replace('Bearer ', '', $headerValue));

        if (in_array($jwtString, ['', null, 'null'], true)) {
            throw new AuthenticationException('Invalid JWT');
        }

        try {
            // decode, validate and check 'exp' field
            $authData = $this->JWTService->decodeJWT($jwtString);
            // check if realm contains web/api/upload
            if (!isset($authData['scope'])
                || !is_array($authData['scope'])
                || !in_array('web/api/upload', $authData['scope'], true)) {
                throw new AuthenticationException('Invalid access scope');
            }
        } catch (Exception) {
            throw new AuthenticationException('Invalid JWT');
        }

        if (!isset($authData['user']['id'], $authData['user']['type'])) {
            throw new AuthenticationException('Invalid JWT');
        }

        // Resolve and validate the user before building the Passport so we can
        // enforce the active flag immediately — not deferred inside the badge loader.
        $u = $this->userRepository->findOneBy(['id' => $authData['user']['id']]);

        if (!$u instanceof User) {
            throw new AuthenticationException('Invalid JWT');
        }

        if (!$u->isActive()) {
            throw new AuthenticationException('Account disabled');
        }

        // 'user' and 'tester' are now both User entities (differentiated by type)
        return new SelfValidatingPassport(
            new UserBadge((string)$u->getId(),
                fn($userIdentifier): ?object => $this->userRepository->findOneBy(['id' => $userIdentifier])
            ), []
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'error' => true,
            'message' => 'Authentication failed',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
