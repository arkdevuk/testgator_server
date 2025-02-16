<?php

namespace App\Services\Authentification;

use App\Traits\GuidAware;

class GuestAuthService
{
    use GuidAware;

    /**
     * This function generates a challenge for the user
     *
     * @return string
     */
    public function getChallenge(): string
    {
        return $this->generateHumanHash(32);
    }

    /**
     * Use this function to get the hash of a challenge
     * do not use custom code as the hash computation might change
     *
     * @param string $challenge
     * @param string $privateKey
     * @return string
     */
    public function getHash(
        string $challenge,
        string $privateKey,
    ): string
    {
        return hash_hmac('sha256', $challenge . $privateKey, $privateKey);
    }

    /**
     * Use this function to validate a hash
     * do not use custom code as the hash computation might change
     *
     * @param string $challenge
     * @param string $hash
     * @param string $privateKey
     * @return bool
     */
    public function validateHash(
        string $challenge,
        string $hash,
        string $privateKey,
    ): bool
    {
        return hash_equals($hash, $this->getHash($challenge, $privateKey));
    }
}
