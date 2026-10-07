<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\TransferController;
use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TransferControllerTest extends TestCase
{
    public function testSerializeTransferMarksOutgoingDirectionForFilteredParticipant(): void
    {
        $controller = new TransferController(
            $this->mockApp(),
            $this->mockDb(),
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(TransferController::class, 'serializeTransfer');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'id' => 10,
            'game_id' => 2,
            'from_participant_id' => 3,
            'from_participant_name' => 'Budi',
            'to_participant_id' => 4,
            'to_participant_name' => 'Negara',
            'amount' => 500,
            'created_at' => '2026-10-07 10:00:00',
        ], 3);

        $this->assertSame('out', $result['direction']);
        $this->assertSame(3, $result['from']['id']);
        $this->assertSame(4, $result['to']['id']);
    }

    public function testSerializeTransferMarksIncomingDirectionForFilteredParticipant(): void
    {
        $controller = new TransferController(
            $this->mockApp(),
            $this->mockDb(),
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(TransferController::class, 'serializeTransfer');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'id' => 11,
            'game_id' => 2,
            'from_participant_id' => 3,
            'from_participant_name' => 'Budi',
            'to_participant_id' => 4,
            'to_participant_name' => 'Negara',
            'amount' => 500,
            'created_at' => '2026-10-07 10:00:00',
        ], 4);

        $this->assertSame('in', $result['direction']);
    }

    public function testTransferAmountValidationRequiresPositiveInteger(): void
    {
        $controller = new TransferController(
            $this->mockApp(),
            $this->mockDb(),
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $method = new ReflectionMethod(TransferController::class, 'validateAmount');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, 0);
    }

    private function mockApp(): Engine
    {
        return $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function mockDb(): SimplePdo
    {
        return $this->getMockBuilder(SimplePdo::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
