<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\AuthController;
use App\Service\Authenticator;
use flight\Engine;
use flight\net\Request;
use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    public function testRequestOtpReturnsChallengeDetails(): void
    {
        $auth = $this->createMock(Authenticator::class);
        $auth->expects($this->once())
            ->method('requestOtp')
            ->with('user@example.com')
            ->willReturn(['challenge_id' => 12, 'expires_in' => 600]);

        $request = $this->requestWithData(['email' => 'user@example.com']);
        $json = [];
        $app = $this->mockApp($request, $json);

        (new AuthController($app, $auth))->requestOtp();

        $this->assertSame(202, $json['status']);
        $this->assertSame(12, $json['payload']['challenge_id']);
    }

    public function testVerifyOtpReturnsAccessToken(): void
    {
        $auth = $this->createMock(Authenticator::class);
        $auth->expects($this->once())
            ->method('verifyOtp')
            ->with(12, '123456')
            ->willReturn([
                'access_token' => 'atk_test',
                'token_type' => 'Bearer',
                'expires_in' => 2592000,
            ]);

        $request = $this->requestWithData(['challenge_id' => 12, 'otp' => '123456']);
        $json = [];
        $app = $this->mockApp($request, $json);

        (new AuthController($app, $auth))->verifyOtp();

        $this->assertSame(200, $json['status']);
        $this->assertSame('atk_test', $json['payload']['access_token']);
    }

    public function testLogoutRevokesBearerToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer atk_test';

        $auth = $this->createMock(Authenticator::class);
        $auth->expects($this->once())
            ->method('logout')
            ->with('atk_test');

        $request = $this->requestWithData([]);
        $json = [];
        $app = $this->mockApp($request, $json);

        (new AuthController($app, $auth))->logout();

        $this->assertSame(200, $json['status']);
        $this->assertSame('Logged out.', $json['payload']['message']);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testLogoutWithoutBearerTokenReturnsUnauthorized(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $auth = $this->createMock(Authenticator::class);
        $auth->expects($this->never())->method('logout');

        $request = $this->requestWithData([]);
        $json = [];
        $app = $this->mockApp($request, $json);

        (new AuthController($app, $auth))->logout();

        $this->assertSame(401, $json['status']);
        $this->assertSame('unauthorized', $json['payload']['error']['code']);
    }

    public function testInvalidRequestReturnsValidationError(): void
    {
        $auth = $this->createMock(Authenticator::class);
        $auth->expects($this->never())->method('requestOtp');

        $request = $this->requestWithData([]);
        $json = [];
        $app = $this->mockApp($request, $json);

        (new AuthController($app, $auth))->requestOtp();

        $this->assertSame(422, $json['status']);
        $this->assertSame('validation_error', $json['payload']['error']['code']);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function requestWithData(array $data): Request
    {
        return new Request([
            'url' => '/api/auth',
            'base' => '/',
            'method' => 'POST',
            'referrer' => '',
            'ip' => '127.0.0.1',
            'ajax' => false,
            'scheme' => 'http',
            'user_agent' => 'phpunit',
            'type' => 'application/json',
            'length' => 0,
            'query' => new Collection(),
            'data' => new Collection($data),
            'cookies' => new Collection(),
            'files' => new Collection(),
            'secure' => false,
            'accept' => 'application/json',
            'proxy_ip' => '',
            'host' => 'localhost',
            'servername' => 'localhost',
        ]);
    }

    /**
     * @param array<string,mixed> $json
     * @return Engine
     */
    private function mockApp(Request $request, array &$json): Engine
    {
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['request', 'json'])
            ->getMock();

        $app->method('request')->willReturn($request);
        $app->method('json')->willReturnCallback(
            static function (array $payload, int $status) use (&$json): void {
                $json = ['payload' => $payload, 'status' => $status];
            }
        );

        return $app;
    }
}
