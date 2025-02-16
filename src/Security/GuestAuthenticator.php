<?php

namespace App\Security;

use App\Classes\Users\AppGuest;
use App\Services\Authentification\JWTService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GuestAuthenticator extends AbstractAuthenticator
{

    protected JWTService $JWTService;

    public function __construct(
        JWTService $JWTService,
    )
    {
        $this->JWTService = $JWTService;
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $headerValue = $request->headers->get('Authorization');
        $jwtString = trim(str_replace('Bearer ', '', $headerValue));

        if ($jwtString === '' || $jwtString === null || $jwtString === 'null') {
            throw new AuthenticationException('Invalid JWT');
        }

        try {
            // decode, validate and check 'exp' field
            $authData = $this->JWTService->decodeJWT($jwtString);
            // check if realm contains web/api/upload
            if (!isset($authData['scope'], $authData['tp']['id'])
                || !is_array($authData['scope'])
                || !in_array('web/api/guest', $authData['scope'], true)) {
                throw new AuthenticationException('Invalid access scope');
            }
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid JWT');
        }

        $tpId = $authData['tp']['id'];

        return new SelfValidatingPassport(new UserBadge($jwtString, function () use ($tpId) {
            return new AppGuest((int)$tpId);
        }));


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
