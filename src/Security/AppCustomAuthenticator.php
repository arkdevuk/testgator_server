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

class AppCustomAuthenticator extends AbstractAuthenticator
{
    public function __construct(protected UserRepository $userRepository, protected JWTService $jwtService)
    {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        // DONE : Implement authenticate() method.
        // get JWT from request
        $headerValue = $request->headers->get('Authorization');
        $jwtString = trim(str_replace('Bearer ', '', $headerValue ?? ''));

        if (in_array($jwtString, ['', null, 'null'], true)) {
            throw new AuthenticationException('Invalid JWT');
        }

        try {
            $authData = $this->jwtService->decodeJWT($jwtString);
        } catch (Exception) {
            throw new AuthenticationException('Invalid JWT');
        }

        // Reject expired tokens before touching the database
        if (($authData['exp'] ?? 0) < time()) {
            throw new AuthenticationException('Invalid JWT');
        }

        // Users and testers are now the same entity (User with a type field),
        // so a single lookup by guid covers both token kinds.
        $u = $this->userRepository->findOneBy(['id' => $authData['guid']]);

        // fallback for tokens issued before the User/Tester merge (tester
        // accounts may have been re-created under a new id on email collision)
        if (!$u && isset($authData['email'])) {
            $u = $this->userRepository->findOneBy(['email' => $authData['email']]);
        }

        // if user not found, error
        if (!$u instanceof User) {
            throw new AuthenticationException('Invalid JWT');
        }

        // Reject deactivated accounts immediately — do not honour any previously
        // issued JWT, regardless of expiry date.
        if (!$u->isActive()) {
            throw new AuthenticationException('Account disabled');
        }

        // if user found, return Passport
        return new SelfValidatingPassport(
            new UserBadge((string) $u->getId(),
                fn ($userIdentifier): ?object => $this->userRepository->findOneBy(['id' => $userIdentifier])
            ), []
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
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

    //    public function start(Request $request, AuthenticationException $authException = null): Response
    //    {
    //        /*
    //         * If you would like this class to control what happens when an anonymous user accesses a
    //         * protected page (e.g. redirect to /login), uncomment this method and make this class
    //         * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
    //         *
    //         * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
    //         */
    //    }
}
