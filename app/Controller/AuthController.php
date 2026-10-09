<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthRateLimitException;
use App\Service\Authenticator;
use InvalidArgumentException;
use RuntimeException;
use flight\Engine;

final class AuthController
{
    /** @var Engine */
    private $app;

    /** @var Authenticator */
    private $auth;

    /**
     * @param Engine $app
     */
    public function __construct(Engine $app, Authenticator $auth)
    {
        $this->app = $app;
        $this->auth = $auth;
    }

    public function requestOtp(): void
    {
        $email = $this->input('email');
        if (!is_string($email)) {
            $this->error('validation_error', 'Email yang kamu masukkan belum valid.', 422);
            return;
        }

        try {
            $result = $this->auth->requestOtp($email);
            $this->app->json([
                'message' => 'Kalau emailnya valid, kode verifikasi sudah dikirim ke email kamu.',
                'challenge_id' => $result['challenge_id'],
                'expires_in' => $result['expires_in'],
            ], 202);
        } catch (AuthRateLimitException $e) {
            $this->app->json([
                'error' => [
                    'code' => 'too_many_requests',
                    'message' => $e->getMessage(),
                    'retry_after' => $e->retryAfter(),
                ],
            ], 429);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        } catch (RuntimeException $e) {
            $this->error('email_delivery_failed', 'Kode verifikasi gagal dikirim. Coba lagi sebentar, ya.', 503);
        }
    }

    public function verifyOtp(): void
    {
        $challengeId = $this->input('challenge_id');
        $otp = $this->input('otp');
        if ((!is_int($challengeId) && !is_string($challengeId))
            || !is_string($otp)
            || !ctype_digit((string) $challengeId)
        ) {
            $this->error('validation_error', 'challenge_id dan OTP wajib diisi.', 422);
            return;
        }

        try {
            $result = $this->auth->verifyOtp((int) $challengeId, $otp);
            $this->app->json($result, 200);
        } catch (InvalidArgumentException $e) {
            $this->error('invalid_otp', $e->getMessage(), 401);
        }
    }

    public function logout(): void
    {
        $token = $this->bearerToken();
        if ($token === null) {
            $this->error('unauthorized', 'Kamu harus login dulu.', 401);
            return;
        }

        try {
            $this->auth->logout($token);
            $this->app->json(['message' => 'Berhasil logout.'], 200);
        } catch (InvalidArgumentException $e) {
            $this->error('unauthorized', $e->getMessage(), 401);
        }
    }

    /**
     * @return mixed
     */
    private function input(string $key)
    {
        return $this->app->request()->data->{$key};
    }

    private function bearerToken(): ?string
    {
        $authorization = $this->app->request()->header('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function error(string $code, string $message, int $status): void
    {
        $this->app->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
