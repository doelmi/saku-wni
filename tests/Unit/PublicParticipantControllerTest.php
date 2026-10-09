<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\PublicParticipantController;
use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PublicParticipantControllerTest extends TestCase
{
    public function testInvalidTokenDoesNotQueryDatabase(): void
    {
        $db = $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $db->expects($this->never())->method('fetchRow');

        $controller = new PublicParticipantController(
            $this->getMockBuilder(Engine::class)->disableOriginalConstructor()->getMock(),
            $db,
            new Config([])
        );

        $method = new ReflectionMethod(PublicParticipantController::class, 'findParticipantByToken');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, 'not valid'));
    }
}
