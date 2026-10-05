<?php

/**
 * Application routes.
 *
 * @var \flight\net\Router $router
 * @var \flight\Engine $app
 * @var \App\Utils\Config $config
 */

use App\Controller\HomeController;
use App\Controller\AuthController;
use App\Controller\PostController;
use App\Middleware\SecurityHeadersMiddleware;
use App\Utils\DatabaseFactory;
use flight\net\Router;

$router->group('', function (Router $router) use ($config) {
    $router->get('/', [HomeController::class, 'index']);

    // Posts demo requires SimplePdo (PostController). Skip when DB is disabled.
    if (DatabaseFactory::isEnabled($config)) {
        $router->get('/posts', [PostController::class, 'index']);
        $router->get('/posts/@id:[0-9]+', [PostController::class, 'show']);

        $router->group('/api', function (Router $router) {
            $router->post('/auth/request-otp', [AuthController::class, 'requestOtp']);
            $router->post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
            $router->get('/posts', [PostController::class, 'apiIndex']);
            $router->get('/posts/@id:[0-9]+', [PostController::class, 'apiShow']);
        });
    }
}, [SecurityHeadersMiddleware::class]);
