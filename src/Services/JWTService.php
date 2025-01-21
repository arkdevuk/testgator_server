<?php

namespace App\Services;

use App\Entity\User;
use Firebase\JWT\JWT;

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
        $env = $_ENV['APP_ENV'] === 'prod' ? 'prod' : 'dev';
        // if password is correct, generate JWT
        $privateKey = file_get_contents("../data/JWT.{$env}/testgator.key");
        // $expire is now + 24 hours
        $payload = [
            'guid' => $u->getId()?->toString(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
            'exp' => $expire,
            'more' => $more,
            'scope' => ['web/app', 'web/api'],
            'ip_hash' => md5($_SERVER['REMOTE_ADDR']), // if ip changes, JWT is invalid
        ];
        return JWT::encode($payload, $privateKey, 'RS256');
    }
}
