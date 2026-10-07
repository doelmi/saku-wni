<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\GameController;
use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use PDO;
use RuntimeException;

class GameControllerTest extends TestCase
{
    /** @var string */
    private $dbPath;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        $this->dbPath = sys_get_temp_dir() . '/flight_games_' . uniqid('', true) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testCloseSetsClosedStatusAndEndedAt(): void
    {
        $db = new SimplePdo('sqlite:' . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $db->exec(
            'CREATE TABLE games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                status TEXT NOT NULL,
                ended_at TEXT NULL,
                deleted_at TEXT NULL
            )'
        );
        $db->prepare(
            'INSERT INTO games (user_id, name, created_at, status, ended_at, deleted_at)
             VALUES (?, ?, ?, ?, NULL, NULL)'
        )->execute([7, 'Friday Monopoly', '2026-10-06 10:00:00', 'active']);

        $json = [];
        $app = $this->mockApp($json, 7);
        $controller = new GameController(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $controller->close(1);

        $game = $db->fetchRow('SELECT status, ended_at FROM games WHERE id = ?', [1]);
        if ($game === null) {
            throw new RuntimeException('Expected game row was not found.');
        }

        $this->assertSame('closed', $game['status']);
        $this->assertNotNull($game['ended_at']);
        $this->assertSame(200, $json['status']);
        $this->assertSame('closed', $json['payload']['status']);
        $this->assertSame($game['ended_at'], $json['payload']['ended_at']);
    }

    public function testCreateAddsDefaultNegaraParticipant(): void
    {
        $db = new SimplePdo('sqlite:' . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $db->exec(
            'CREATE TABLE games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                status TEXT NOT NULL,
                ended_at TEXT NULL,
                deleted_at TEXT NULL
            )'
        );
        $db->exec(
            'CREATE TABLE participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )'
        );

        $json = [];
        $app = $this->mockApp($json, 7, 'Game Baru');
        $controller = new GameController(
            $app,
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        );

        $controller->create();

        $participant = $db->fetchRow(
            'SELECT game_id, name, created_at, updated_at, deleted_at
             FROM participants
             WHERE game_id = ?',
            [1]
        );

        if ($participant === null) {
            throw new RuntimeException('Expected default participant was not found.');
        }

        $this->assertSame(201, $json['status']);
        $this->assertSame('Negara', $participant['name']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $participant['created_at']
        );
        $this->assertSame($participant['created_at'], $participant['updated_at']);
        $this->assertNull($participant['deleted_at']);
        $this->assertSame(1, (int) $participant['game_id']);
    }

    /**
     * @param array<string,mixed> $json
     * @param string|null $gameName
     * @return Engine
     */
    private function mockApp(array &$json, int $userId, ?string $gameName = null): Engine
    {
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['json', 'get', 'request'])
            ->getMock();

        $app->method('json')->willReturnCallback(
            static function (array $payload, int $status) use (&$json): void {
                $json = ['payload' => $payload, 'status' => $status];
            }
        );
        $app->method('get')->with('auth.user_id')->willReturn($userId);
        if ($gameName !== null) {
            $request = new \stdClass();
            $request->data = (object) ['name' => $gameName];
            $app->method('request')->willReturn($request);
        }

        return $app;
    }
}
