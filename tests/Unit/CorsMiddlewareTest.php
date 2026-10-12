<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\CorsMiddleware;
use App\Utils\Config;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    public function testAllowedOriginGetsCorsHeaders(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
        $headers = [];
        $response = $this->responseMock($headers);
        $request = new Request();
        $request->method = 'POST';

        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['request', 'response'])
            ->getMock();
        $app->method('request')->willReturn($request);
        $app->method('response')->willReturn($response);

        (new CorsMiddleware($app, new Config([])))->before([]);

        $this->assertSame('http://localhost:3000', $headers['Access-Control-Allow-Origin']);
        $this->assertStringContainsString('Authorization', $headers['Access-Control-Allow-Headers']);

        unset($_SERVER['HTTP_ORIGIN']);
    }

    public function testOptionsRequestReturnsNoContent(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
        $headers = [];
        $response = $this->responseMock($headers);
        $request = new Request();
        $request->method = 'OPTIONS';

        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['request', 'response', 'halt'])
            ->getMock();
        $app->method('request')->willReturn($request);
        $app->method('response')->willReturn($response);
        $app->expects($this->once())->method('halt')->with(204, '');

        (new CorsMiddleware($app, new Config([])))->before([]);

        unset($_SERVER['HTTP_ORIGIN']);
    }

    public function testRequestListenerHandlesPreflightBeforeRouting(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
        $headers = [];
        $response = $this->responseMock($headers);
        $request = new Request();
        $request->url = '/api/auth/request-otp';
        $request->method = 'OPTIONS';

        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['response', 'halt'])
            ->getMock();
        $app->method('response')->willReturn($response);
        $app->expects($this->once())->method('halt')->with(204, '');

        (new CorsMiddleware($app, new Config([])))->onRequest($request);

        $this->assertSame('http://localhost:3000', $headers['Access-Control-Allow-Origin']);
        unset($_SERVER['HTTP_ORIGIN']);
    }

    /**
     * @param array<string,string> $headers
     */
    private function responseMock(array &$headers): Response
    {
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['header'])
            ->getMock();
        $response->method('header')
            ->willReturnCallback(static function ($name, $value = null) use (&$headers, $response) {
                if ($value !== null) {
                    $headers[$name] = $value;
                }

                return $response;
            });

        return $response;
    }
}
