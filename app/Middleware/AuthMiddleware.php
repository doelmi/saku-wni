<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Config;
use DateTimeImmutable;
use DateTimeZone;
use flight\database\SimplePdo;
use flight\Engine;

final class AuthMiddleware
{
    /** @var Engine */
    private $app;

    /** @var SimplePdo */
    private $db;

    /** @var Config */
    private $config;

    public function __construct(Engine $app, SimplePdo $db, Config $config)
    {
        $this->app = $app;
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function before(array $params): void
    {
        $authorization = $this->app->request()->header('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            $this->unauthorized('Authentication required.');
            return;
        }

        $tokenHash = hash('sha256', $matches[1]);
        $token = $this->db->fetchRow(
            'SELECT user_id FROM access_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?
             LIMIT 1',
            [$tokenHash, $this->now()]
        );

        if ($token === null || count($token) === 0) {
            $this->unauthorized('Invalid or expired access token.');
            return;
        }

        $this->app->set('auth.user_id', (int) $token['user_id']);
    }

    private function now(): string
    {
        $timezone = (string) $this->config->get('app.timezone', date_default_timezone_get());

        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d H:i:s');
    }

    private function unauthorized(string $message): void
    {
        $this->app->response()->header('WWW-Authenticate', 'Bearer');
        $this->app->jsonHalt([
            'error' => [
                'code' => 'unauthorized',
                'message' => $message,
            ],
        ], 401);
    }
}
