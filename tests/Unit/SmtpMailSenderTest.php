<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\SmtpMailSender;
use App\Utils\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SmtpMailSenderTest extends TestCase
{
    public function testMissingSmtpHostFailsBeforeConnecting(): void
    {
        $sender = new SmtpMailSender(new Config([
            'mail' => [
                'host' => '',
                'from' => 'no-reply@example.com',
            ],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP host and sender address are not configured.');
        $sender->send('user@example.com', 'Test', 'Body');
    }

    public function testUnsupportedEncryptionFailsBeforeConnecting(): void
    {
        $sender = new SmtpMailSender(new Config([
            'mail' => [
                'host' => 'smtp.example.com',
                'from' => 'no-reply@example.com',
                'encryption' => 'invalid',
            ],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported SMTP encryption: invalid');
        $sender->send('user@example.com', 'Test', 'Body');
    }
}
