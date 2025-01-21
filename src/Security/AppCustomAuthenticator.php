<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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
    protected EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em,
    )
    {
        $this->em = $em;

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
        $jwtString = trim(str_replace('Bearer ', '', $headerValue));

        if ($jwtString === '' || $jwtString === null || $jwtString === 'null') {
            throw new AuthenticationException('Invalid JWT');
        }

        // get private key in /data/JWT/key.private.key
        //$privateKey = file_get_contents('/data/JWT/key.private.key');
        // get public key in /data/JWT/key.pub
        $appRootDir = dirname(__DIR__, 2);
        $publicKey = file_get_contents($appRootDir . '/data/JWT/key.pub');
        // decode JWT
        $jwt = JWT::decode($jwtString, new Key($publicKey, 'RS256'));
        $authData = $this->objectToArray($jwt);
        // get user from DB with $authData['guid']
        $u = $this->em->getRepository(User::class)->findOneBy(['id' => $authData['guid']]);
        // if user not found, error
        if (!$u) {
            throw new AuthenticationException('Invalid JWT');
        }
        // check 'exp' field
        if ($authData['exp'] < time()) {
            throw new AuthenticationException('Invalid JWT');
        }

        // if user found, return Passport
        $self = &$this;
        $passport = new SelfValidatingPassport(
            new UserBadge($u->getId(),
                static function ($userIdentifier) use ($self) {
                    $u = $self->em->getRepository(User::class)
                        ->findOneBy(['id' => $userIdentifier]);
                    if (!$u instanceof User) {
                        return null;
                    }

                    return $u;
                }
            ), []
        );

        return $passport;
    }

    public function objectToArray($obj): array
    {
        $arr = [];
        foreach ($obj as $key => $value) {
            $arr[$key] = $value;
            // recursive call if the value is an object
            if (is_object($value)) {
                $arr[$key] = $this->objectToArray($value);
            }
        }
        return $arr;
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
