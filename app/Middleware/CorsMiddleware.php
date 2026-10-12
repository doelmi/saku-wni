<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Config;
use flight\Engine;
use flight\net\Request;

final class CorsMiddleware
{
    /** @var Engine */
    private $app;

    /** @var Config */
    private $config;

    public function __construct(Engine $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function before(array $params): void
    {
        $request = $this->app->request();
        $this->apply($request);

        if (strtoupper($request->method) === 'OPTIONS') {
            $this->app->halt(204, '');
        }
    }

    /**
     * Flight handles OPTIONS before route middleware, so this listener is
     * registered on flight.request.received from bootstrap.php.
     */
    public function onRequest(Request $request): void
    {
        $path = (string) parse_url($request->url, PHP_URL_PATH);
        if ($path !== '/api' && strpos($path, '/api/') !== 0) {
            return;
        }

        $this->apply($request);

        if (strtoupper($request->method) === 'OPTIONS') {
            $this->app->halt(204, '');
        }
    }

    private function apply(Request $request): void
    {
        $origin = $request->header('Origin');
        $response = $this->app->response();

        $response->header('Vary', 'Origin');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        $response->header('Access-Control-Max-Age', '600');

        if ($origin !== '' && in_array($origin, $this->allowedOrigins(), true)) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }
    }

    /**
     * @return array<int,string>
     */
    private function allowedOrigins(): array
    {
        $origins = $this->config->get(
            'cors.allowed_origins',
            ['http://localhost:3000', 'http://127.0.0.1:3000']
        );

        if (is_string($origins)) {
            $origins = explode(',', $origins);
        }

        if (!is_array($origins)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($origin): string {
            return trim((string) $origin);
        }, $origins), static function (string $origin): bool {
            return $origin !== '';
        }));
    }
}
