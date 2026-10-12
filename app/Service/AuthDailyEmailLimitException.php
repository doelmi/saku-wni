<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AuthDailyEmailLimitException extends RuntimeException
{
    /** @var int */
    private $retryAfter;

    public function __construct(int $retryAfter)
    {
        parent::__construct('Jatah pengiriman email hari ini sudah habis. Coba lagi besok, ya.');
        $this->retryAfter = $retryAfter;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
