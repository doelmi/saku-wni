<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AuthRateLimitException extends RuntimeException
{
    /** @var int */
    private $retryAfter;

    public function __construct(int $retryAfter)
    {
        parent::__construct('Tunggu sebentar sebelum minta OTP lagi, ya.');
        $this->retryAfter = $retryAfter;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
