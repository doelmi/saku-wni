<?php

declare(strict_types=1);

namespace App\Service;

use App\Utils\Config;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use flight\database\SimplePdo;

final class AuthService implements Authenticator
{
    /** @var SimplePdo */
    private $db;

    /** @var Config */
    private $config;

    /** @var EmailSender */
    private $emailSender;

    public function __construct(SimplePdo $db, Config $config, EmailSender $emailSender)
    {
        $this->db = $db;
        $this->config = $config;
        $this->emailSender = $emailSender;
    }

    /**
     * @return array{challenge_id:int,expires_in:int}
     */
    public function requestOtp(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $now = $this->now();
        $cooldown = max(1, (int) $this->config->get('auth.otp_resend_cooldown', 60));
        $otpTtl = max(60, (int) $this->config->get('auth.otp_ttl', 600));

        $user = $this->db->fetchRow('SELECT id FROM users WHERE email = ?', [$email]);
        if ($user === null || count($user) === 0) {
            $statement = $this->db->prepare(
                'INSERT INTO users (email, created_at, updated_at) VALUES (?, ?, ?)'
            );
            $statement->execute([$email, $now, $now]);
            $userId = (int) $this->db->lastInsertId();
        } else {
            $userId = (int) $user['id'];
        }

        $lastChallenge = $this->db->fetchRow(
            'SELECT created_at FROM otp_challenges
             WHERE user_id = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1',
            [$userId]
        );
        if ($lastChallenge !== null
            && count($lastChallenge) > 0
            && is_string($lastChallenge['created_at'])
        ) {
            $elapsed = time() - strtotime($lastChallenge['created_at']);
            if ($elapsed < $cooldown) {
                throw new AuthRateLimitException($cooldown - max(0, $elapsed));
            }
        }

        $this->db->prepare(
            'UPDATE otp_challenges SET consumed_at = ?
             WHERE user_id = ? AND consumed_at IS NULL'
        )->execute([$now, $userId]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = $this->timeAfter($otpTtl);
        $codeHash = password_hash($otp, PASSWORD_DEFAULT);
        if ($codeHash === false) {
            throw new RuntimeException('Unable to create OTP.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO otp_challenges
                (user_id, code_hash, expires_at, attempts, consumed_at, created_at)
             VALUES (?, ?, ?, 0, NULL, ?)'
        );
        $statement->execute([$userId, $codeHash, $expiresAt, $now]);
        $challengeId = (int) $this->db->lastInsertId();

        $subject = (string) $this->config->get('mail.otp_subject', 'Your login verification code');
        try {
            $this->emailSender->send(
                $email,
                $subject,
                "Your verification code is {$otp}.\n\n"
                . "This code expires in " . (int) ceil($otpTtl / 60) . " minutes.\n"
                . "If you did not request this code, you can ignore this email."
            );
        } catch (\Throwable $e) {
            $this->db->prepare(
                'UPDATE otp_challenges SET consumed_at = ? WHERE id = ?'
            )->execute([$this->now(), $challengeId]);
            throw new RuntimeException('Unable to send OTP email.', 0, $e);
        }

        return [
            'challenge_id' => $challengeId,
            'expires_in' => $otpTtl,
        ];
    }

    /**
     * @return array{access_token:string,token_type:string,expires_in:int}
     */
    public function verifyOtp(int $challengeId, string $otp): array
    {
        if ($challengeId < 1 || !preg_match('/^\d{6}$/', $otp)) {
            throw new InvalidArgumentException('The challenge_id and otp are invalid.');
        }

        $maxAttempts = max(1, (int) $this->config->get('auth.otp_max_attempts', 5));
        $challenge = $this->db->fetchRow(
            'SELECT id, user_id, code_hash, expires_at, attempts, consumed_at
             FROM otp_challenges WHERE id = ? LIMIT 1',
            [$challengeId]
        );

        if ($challenge === null || count($challenge) === 0 || $challenge['consumed_at'] !== null) {
            throw new InvalidArgumentException('The OTP is invalid or has expired.');
        }

        if (strtotime((string) $challenge['expires_at']) <= time()) {
            throw new InvalidArgumentException('The OTP is invalid or has expired.');
        }

        $attempts = (int) $challenge['attempts'];
        if ($attempts >= $maxAttempts) {
            throw new InvalidArgumentException('The OTP is invalid or has expired.');
        }

        if (!password_verify($otp, (string) $challenge['code_hash'])) {
            $this->db->prepare(
                'UPDATE otp_challenges SET attempts = attempts + 1 WHERE id = ?'
            )->execute([$challengeId]);
            throw new InvalidArgumentException('The OTP is invalid or has expired.');
        }

        $now = $this->now();
        $updated = $this->db->prepare(
            'UPDATE otp_challenges SET consumed_at = ?
             WHERE id = ? AND consumed_at IS NULL'
        );
        $updated->execute([$now, $challengeId]);
        if ($updated->rowCount() !== 1) {
            throw new InvalidArgumentException('The OTP is invalid or has expired.');
        }

        $tokenTtl = max(300, (int) $this->config->get('auth.access_token_ttl', 2592000));
        $token = 'atk_' . self::base64UrlEncode(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = $this->timeAfter($tokenTtl);

        $this->db->prepare(
            'INSERT INTO access_tokens
                (user_id, token_hash, expires_at, revoked_at, created_at)
             VALUES (?, ?, ?, NULL, ?)'
        )->execute([(int) $challenge['user_id'], $tokenHash, $expiresAt, $now]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $tokenTtl,
        ];
    }

    public function logout(string $accessToken): void
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new InvalidArgumentException('A bearer token is required.');
        }

        $this->db->prepare(
            'UPDATE access_tokens
             SET revoked_at = ?
             WHERE token_hash = ? AND revoked_at IS NULL'
        )->execute([
            $this->now(),
            hash('sha256', $accessToken),
        ]);
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return $email;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', $this->timezone()))->format('Y-m-d H:i:s');
    }

    private function timeAfter(int $seconds): string
    {
        return (new DateTimeImmutable('now', $this->timezone()))
            ->modify('+' . $seconds . ' seconds')
            ->format('Y-m-d H:i:s');
    }

    private function timezone(): DateTimeZone
    {
        $timezone = (string) $this->config->get('app.timezone', date_default_timezone_get());

        return new DateTimeZone($timezone);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
