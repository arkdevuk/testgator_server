<?php

namespace App\Services\Authentification;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    public function getJWT(User $user, bool $rememberMe = false, array $more = []): string
    {
        $expire = $this->getExpireTime($rememberMe);
        return $this->generateJWT($user, $expire, $more);
    }

    private function getExpireTime(bool $rememberMe = false): int
    {
        // default is 1 minutes for testing
        //$expire = time() + 60 * 1;
        // PROD default
        $expire = time() + (60 * 60 * 24);
        if ($rememberMe) {
            // set for 30 days
            $expire = time() + (60 * 60 * 24 * 30);
        }
        return $expire;
    }

    private function generateJWT(User $u, int $expire, array $more = [])
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
     *
     * @param array $payload
     * @return string
     */
    public function generateToken(array $payload = [])
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
     * @param string $jwt
     * @return array
     * @throws \Exception
     */
    public function decodeJWT(string $jwt): array
    {
        $env = $_ENV['APP_ENV'] === 'prod' ? 'prod' : 'dev';
        // if password is correct, generate JWT
        $privateKey = file_get_contents("../data/JWT.{$env}/testgator.pub");
        // decode JWT
        $content = JWT::decode($jwt, new Key($privateKey, 'RS256'));
        $authData = $this->objectToArray($content);

        if (isset($authData['exp']) && $authData['exp'] < time()) {
            throw new \Exception('Invalid JWT');
        }

        return $authData;
    }

    /**
     * Recursive function to convert an object to an array
     *
     * @param $obj
     * @return array
     */
    private function objectToArray($obj): array
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
}
