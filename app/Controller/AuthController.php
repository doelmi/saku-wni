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
            $this->error('validation_error', 'A valid email address is required.', 422);
            return;
        }

        try {
            $result = $this->auth->requestOtp($email);
            $this->app->json([
                'message' => 'If the email is valid, a verification code has been sent.',
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
            $this->error('email_delivery_failed', 'Unable to send the verification code.', 503);
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
            $this->error('validation_error', 'challenge_id and otp are required.', 422);
            return;
        }

        try {
            $result = $this->auth->verifyOtp((int) $challengeId, $otp);
            $this->app->json($result, 200);
        } catch (InvalidArgumentException $e) {
            $this->error('invalid_otp', $e->getMessage(), 401);
        }
    }

    /**
     * @return mixed
     */
    private function input(string $key)
    {
        return $this->app->request()->data->{$key};
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
