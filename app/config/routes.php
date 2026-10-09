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
use App\Controller\ApiDocsController;
use App\Controller\GameController;
use App\Controller\ParticipantController;
use App\Controller\PublicParticipantController;
use App\Controller\TransferController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Utils\DatabaseFactory;
use flight\net\Router;

$router->group('', function (Router $router) use ($config) {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/docs', [ApiDocsController::class, 'ui']);
    $router->get('/api/openapi', [ApiDocsController::class, 'openapi']);

    // Database-backed API routes require SimplePdo. Skip when DB is disabled.
    if (DatabaseFactory::isEnabled($config)) {
        $router->group('/api', function (Router $router) {
            $router->group('/public/participants', function (Router $router) {
                $router->get('/@token:[A-Za-z0-9_-]+', [PublicParticipantController::class, 'show']);
                $router->get('/@token:[A-Za-z0-9_-]+/transfers', [PublicParticipantController::class, 'history']);
                $router->get(
                    '/@token:[A-Za-z0-9_-]+/stream',
                    [PublicParticipantController::class, 'stream']
                )->streamWithHeaders([
                    'Content-Type' => 'text/event-stream; charset=utf-8',
                    'Cache-Control' => 'no-cache, no-transform',
                    'X-Accel-Buffering' => 'no',
                    'Access-Control-Allow-Origin' => '*',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'Referrer-Policy' => 'no-referrer',
                    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
                    'Permissions-Policy' => 'geolocation=()',
                ]);
            });
            $router->group('/auth', function (Router $router) {
                $router->post('/request-otp', [AuthController::class, 'requestOtp']);
                $router->post('/verify-otp', [AuthController::class, 'verifyOtp']);
                $router->group('', function (Router $router) {
                    $router->post('/logout', [AuthController::class, 'logout']);
                }, [AuthMiddleware::class]);
            });
            $router->group('/games', function (Router $router) {
                $router->get('/', [GameController::class, 'index']);
                $router->post('/', [GameController::class, 'create']);
                $router->patch('/@id:[0-9]+', [GameController::class, 'update']);
                $router->patch('/@id:[0-9]+/close', [GameController::class, 'close']);
                $router->delete('/@id:[0-9]+', [GameController::class, 'delete']);
                $router->group('/@game_id:[0-9]+/participants', function (Router $router) {
                    $router->get('/', [ParticipantController::class, 'index']);
                    $router->post('/', [ParticipantController::class, 'create']);
                    $router->patch(
                        '/@participant_id:[0-9]+',
                        [ParticipantController::class, 'update']
                    );
                    $router->patch(
                        '/@participant_id:[0-9]+/status',
                        [ParticipantController::class, 'updateStatus']
                    );
                    $router->delete(
                        '/@participant_id:[0-9]+',
                        [ParticipantController::class, 'delete']
                    );
                });
                $router->group('/@game_id:[0-9]+/transfers', function (Router $router) {
                    $router->get('/', [TransferController::class, 'index']);
                    $router->post('/', [TransferController::class, 'transfer']);
                });
            }, [AuthMiddleware::class]);
        });
    }
}, [SecurityHeadersMiddleware::class]);
