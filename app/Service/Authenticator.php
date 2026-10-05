<?php

declare(strict_types=1);

namespace App\Service;

interface Authenticator
{
    /**
     * @return array{challenge_id:int,expires_in:int}
     */
    public function requestOtp(string $email): array;

    /**
     * @return array{access_token:string,token_type:string,expires_in:int}
     */
    public function verifyOtp(int $challengeId, string $otp): array;
}
