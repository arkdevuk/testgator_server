<?php

namespace App\Traits;

trait GuidAware
{
    protected function generateHumanHash(int $length = 64): string
    {
        $hash = '';
        while (strlen($hash) < $length) {
            try {
                $hash .= hash('sha512', random_bytes($length));
            } catch (\Exception $e) {
                $hash .= hash('sha512', random_int(0, $length * 100000));
            }
        }

        return substr($hash, 0, $length);
    }
}
