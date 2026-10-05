<?php

declare(strict_types=1);

namespace App\Service;

use App\Utils\Config;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpMailSender implements EmailSender
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        $host = trim((string) $this->config->get('mail.host', ''));
        $from = trim((string) $this->config->get('mail.from', ''));
        if ($host === '' || $from === '') {
            throw new RuntimeException('SMTP host and sender address are not configured.');
        }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = $host;
            $mailer->Port = max(1, (int) $this->config->get('mail.port', 587));
            $mailer->SMTPAuth = trim((string) $this->config->get('mail.username', '')) !== '';
            $mailer->Username = (string) $this->config->get('mail.username', '');
            $mailer->Password = (string) $this->config->get('mail.password', '');
            $mailer->Timeout = max(1, (int) $this->config->get('mail.timeout', 15));
            $mailer->CharSet = 'UTF-8';

            $encryption = strtolower(trim((string) $this->config->get('mail.encryption', 'tls')));
            if ($encryption === 'ssl' || $encryption === 'smtps') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls' || $encryption === 'starttls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption !== '' && $encryption !== 'none') {
                throw new RuntimeException('Unsupported SMTP encryption: ' . $encryption);
            } else {
                $mailer->SMTPAutoTLS = false;
            }

            $mailer->setFrom(
                $from,
                (string) $this->config->get('mail.from_name', '')
            );
            $mailer->addAddress($recipient);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->isHTML(false);
            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Unable to send email through SMTP.', 0, $e);
        }
    }
}
