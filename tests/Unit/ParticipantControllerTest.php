<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\ParticipantController;
use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ParticipantControllerTest extends TestCase
{
    public function testParticipantNameValidationRejectsBlankName(): void
    {
        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $app->method('get')->with('auth.user_id')->willReturn(42);

        $controller = new ParticipantController(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(ParticipantController::class, 'validateName');
        $method->setAccessible(true);
        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, ' ');
    }

    public function testParticipantStatusValidationRejectsUnknownStatus(): void
    {
        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $app->method('get')->with('auth.user_id')->willReturn(42);

        $controller = new ParticipantController(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(ParticipantController::class, 'validateStatus');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, 'retired');
    }

    public function testUserParticipantNameValidationRejectsNegaraName(): void
    {
        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $app->method('get')->with('auth.user_id')->willReturn(42);

        $controller = new ParticipantController(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(ParticipantController::class, 'validateUserParticipantName');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, 'negara');
    }
}
