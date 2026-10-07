<?php

declare(strict_types=1);

namespace App\Controller;

use App\Utils\Config;
use flight\Engine;

final class ApiDocsController
{
    /** @var Engine */
    private $app;

    /** @var Config */
    private $config;

    /**
     * @param Engine $app
     */
    public function __construct(Engine $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    public function openapi(): void
    {
        /** @var array<string,mixed> $document */
        $document = require dirname(__DIR__) . '/config/openapi.php';
        $document['servers'] = [
            ['url' => $this->config->baseUrl()],
        ];

        $this->app->json($document, 200);
    }

    public function ui(): void
    {
        $nonce = htmlspecialchars((string) $this->app->get('csp_nonce'), ENT_QUOTES, 'UTF-8');
        $specUrl = htmlspecialchars(
            $this->config->baseUrl() . 'api/openapi',
            ENT_QUOTES,
            'UTF-8'
        );

        $html = '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saku WNI API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script nonce="' . $nonce . '" src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script nonce="' . $nonce . '">
window.onload = function () {
    window.ui = SwaggerUIBundle({
        url: "' . $specUrl . '",
        dom_id: "#swagger-ui",
        deepLinking: true,
        displayRequestDuration: true,
        tryItOutEnabled: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIBundle.SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
    });
};
</script>
</body>
</html>';

        $this->app->response()->header('Content-Type', 'text/html; charset=UTF-8');
        $this->app->response()->write($html);
    }
}
