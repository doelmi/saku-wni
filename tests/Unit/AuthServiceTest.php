<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\AuthService;
use App\Service\EmailSender;
use App\Utils\Config;
use flight\database\SimplePdo;
use PHPUnit\Framework\TestCase;
use PDOStatement;
use ReflectionMethod;

class AuthServiceTest extends TestCase
{
    public function testNowUsesConfiguredTimezone(): void
    {
        $service = $this->serviceWithTimezone('Asia/Jakarta');

        $method = new ReflectionMethod(AuthService::class, 'now');
        $method->setAccessible(true);
        $actual = $method->invoke($service);
        $expected = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))
            ->format('Y-m-d H:i');

        $this->assertSame($expected, substr((string) $actual, 0, 16));
    }

    public function testFutureTimeUsesConfiguredTimezone(): void
    {
        $service = $this->serviceWithTimezone('Asia/Jakarta');
        $method = new ReflectionMethod(AuthService::class, 'timeAfter');
        $method->setAccessible(true);

        $actual = (string) $method->invoke($service, 600);
        $parsed = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $actual,
            new \DateTimeZone('Asia/Jakarta')
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
        $this->assertGreaterThanOrEqual(time() + 599, $parsed->getTimestamp());
        $this->assertLessThanOrEqual(time() + 601, $parsed->getTimestamp());
    }

    public function testLogoutRevokesHashedToken(): void
    {
        $statement = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();
        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function (array $params): bool {
                return count($params) === 2
                    && is_string($params[0])
                    && hash_equals(hash('sha256', 'atk_test'), (string) $params[1]);
            }))
            ->willReturn(true);

        $database = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();
        $database->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE access_tokens'))
            ->willReturn($statement);

        $service = new AuthService($database, new Config([
            'app' => ['timezone' => 'Asia/Jakarta'],
        ]), $this->createMock(EmailSender::class));

        $service->logout('atk_test');
    }

    private function serviceWithTimezone(string $timezone): AuthService
    {
        $database = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emailSender = $this->createMock(EmailSender::class);

        return new AuthService($database, new Config([
            'app' => ['timezone' => $timezone],
        ]), $emailSender);
    }
}
