<?php

declare(strict_types=1);

namespace App\Service;

interface EmailSender
{
    public function send(string $recipient, string $subject, string $body): void;
}
