<?php

namespace App\Services\Authentification;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTService
{
    public function getJWT(UserInterface $user, bool $rememberMe = false, array $more = []): string
    {
        $expire = $this->getExpireTime($rememberMe);
        return $this->generateJWT($user, $expire, $more);
    }

    private function getExpireTime(bool $rememberMe = false): int
    {
        if ($rememberMe) {
            return time() + (60 * 60 * 24 * 30);
        }
        return time() + (60 * 60 * 24);
    }

    private function generateJWT(UserInterface $u, int $expire, array $more = [])
    {
        $payload = [
            'guid' => $u->getId()?->toString(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
            'exp' => $expire,
            'more' => $more,
            'scope' => ['web/app', 'web/api'],
        ];
        return $this->generateToken($payload);
    }

    /**
     * use this function to generate a token
     */
    public function generateToken(array $payload = []): string
    {
        $env = $_ENV['APP_ENV'] === 'prod' ? 'prod' : 'dev';
        // if password is correct, generate JWT
        $privateKey = file_get_contents("../data/JWT.{$env}/testgator.key");
        // $expire is now + 24 hours
        $payload = [
            ...$payload,
            'ip_hash' => md5($_SERVER['REMOTE_ADDR']), // if ip changes, JWT is invalid
        ];
        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * This function decode a JWT token
     * and return the payload
     * if the payload contains the 'exp' field it also checks if the token is expired
     *
     * @throws Exception
     */
    public function decodeJWT(string $jwt): array
    {
        $env = $_ENV['APP_ENV'] === 'prod' ? 'prod' : 'dev';
        // if password is correct, generate JWT
        $privateKey = file_get_contents("../data/JWT.{$env}/testgator.pub");
        // decode JWT
        $content = JWT::decode($jwt, new Key($privateKey, 'RS256'));
        $authData = self::jwtPayloadToArray($content);

        if (isset($authData['exp']) && $authData['exp'] < time()) {
            throw new Exception('Invalid JWT');
        }

        return $authData;
    }

    /**
     * Recursively converts a stdClass (as returned by firebase/php-jwt) to a plain array.
     */
    public static function jwtPayloadToArray(mixed $obj): array
    {
        $arr = [];
        foreach ((array)$obj as $key => $value) {
            $arr[$key] = is_object($value) ? self::jwtPayloadToArray($value) : $value;
        }
        return $arr;
    }
}
