<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\AuthMiddleware;
use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    public function testValidBearerTokenStoresAuthenticatedUserId(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';

        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->once())
            ->method('fetchRow')
            ->with(
                $this->stringContains('SELECT user_id FROM access_tokens'),
                $this->callback(static function (array $params): bool {
                    return count($params) === 2
                        && hash_equals(hash('sha256', 'test-token'), (string) $params[0])
                        && is_string($params[1]);
                })
            )
            ->willReturn(new Collection(['user_id' => 42]));

        $stored = [];
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['set'])
            ->addMethods(['request'])
            ->getMock();
        $app->method('request')->willReturn(new Request());
        $app->expects($this->once())
            ->method('set')
            ->with('auth.user_id', 42)
            ->willReturnCallback(static function (string $key, $value) use (&$stored): void {
                $stored[$key] = $value;
            });

        $middleware = new AuthMiddleware(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );
        $middleware->before([]);

        $this->assertSame(42, $stored['auth.user_id']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testMissingBearerTokenReturnsUnauthorizedJson(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('fetchRow');

        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['jsonHalt', 'request', 'response'])
            ->getMock();
        $app->method('request')->willReturn(new Request());
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['header'])
            ->getMock();
        $response->method('header')->willReturnSelf();
        $app->method('response')->willReturn($response);
        $app->expects($this->once())
            ->method('jsonHalt')
            ->with(
                $this->callback(static function (array $payload): bool {
                    return $payload['error']['code'] === 'unauthorized';
                }),
                401
            );

        $middleware = new AuthMiddleware(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );
        $middleware->before([]);
    }
}
