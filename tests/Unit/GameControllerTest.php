<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\GameController;
use App\Utils\Config;
use DateTimeImmutable;
use DateTimeZone;
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
                balance INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                public_token TEXT NULL,
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

    public function testDailyLimitCountsDeletedGamesButOnlyForTheCurrentUserAndDay(): void
    {
        $db = $this->createGameDatabase();
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
            ->format('Y-m-d');
        $yesterday = (new DateTimeImmutable($today, new DateTimeZone('Asia/Jakarta')))
            ->modify('-1 day')->format('Y-m-d');

        for ($i = 0; $i < 5; $i++) {
            $db->prepare(
                'INSERT INTO games (user_id, name, created_at, status, deleted_at)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([7, 'Game ' . $i, $today . ' 10:00:00', 'active',
                $i === 0 ? $today . ' 11:00:00' : null]);
        }
        $db->prepare(
            'INSERT INTO games (user_id, name, created_at, status) VALUES (?, ?, ?, ?)'
        )->execute([7, 'Kemarin', $yesterday . ' 23:59:59', 'active']);
        $db->prepare(
            'INSERT INTO games (user_id, name, created_at, status) VALUES (?, ?, ?, ?)'
        )->execute([8, 'User lain', $today . ' 10:00:00', 'active']);

        $json = [];
        (new GameController(
            $this->mockApp($json, 7, 'Game keenam'),
            $db,
            new Config(['app' => ['timezone' => 'Asia/Jakarta']])
        ))->create();

        $this->assertSame(429, $json['status']);
        $this->assertSame('game_daily_limit_reached', $json['payload']['error']['code']);
        $this->assertSame(6, $this->countRows(
            $db, 'SELECT COUNT(*) AS total FROM games WHERE user_id = ?', [7]
        ));
        $this->assertSame(0, $this->countRows(
            $db, 'SELECT COUNT(*) AS total FROM participants'
        ));
    }

    public function testConfiguredDailyLimitAllowsUpToLimitThenResetsForAnotherUser(): void
    {
        $db = $this->createGameDatabase();
        $config = new Config(['app' => ['timezone' => 'Asia/Jakarta'],
            'games' => ['daily_create_limit' => 2]]);
        $json = [];

        for ($i = 0; $i < 3; $i++) {
            (new GameController($this->mockApp($json, 7, 'Game ' . $i), $db, $config))->create();
            $this->assertSame($i < 2 ? 201 : 429, $json['status']);
        }

        (new GameController($this->mockApp($json, 8, 'Game user lain'), $db, $config))->create();
        $this->assertSame(201, $json['status']);
        $this->assertSame(3, $this->countRows($db, 'SELECT COUNT(*) AS total FROM games'));
        $this->assertSame(3, $this->countRows($db, 'SELECT COUNT(*) AS total FROM participants'));
    }

    public function testGlobalLimitCountsAllUsersAndDeletedGamesWithoutAffectingYesterday(): void
    {
        $db = $this->createGameDatabase();
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
            ->format('Y-m-d');
        $yesterday = (new DateTimeImmutable($today, new DateTimeZone('Asia/Jakarta')))
            ->modify('-1 day')->format('Y-m-d');
        $config = new Config([
            'app' => ['timezone' => 'Asia/Jakarta'],
            'games' => ['daily_create_limit' => 5, 'global_daily_create_limit' => 2],
        ]);
        $json = [];

        $db->prepare(
            'INSERT INTO games (user_id, name, created_at, status, deleted_at)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([7, 'Dihapus', $today . ' 10:00:00', 'active', $today . ' 11:00:00']);
        $db->prepare(
            'INSERT INTO games (user_id, name, created_at, status)
             VALUES (?, ?, ?, ?)'
        )->execute([8, 'Kemarin', $yesterday . ' 23:59:59', 'active']);

        (new GameController($this->mockApp($json, 8, 'Game kedua'), $db, $config))->create();
        $this->assertSame(201, $json['status']);

        (new GameController($this->mockApp($json, 9, 'Game ketiga'), $db, $config))->create();
        $this->assertSame(429, $json['status']);
        $this->assertSame('game_global_daily_limit_reached', $json['payload']['error']['code']);
        $this->assertSame(3, $this->countRows($db, 'SELECT COUNT(*) AS total FROM games'));
        $this->assertSame(1, $this->countRows($db, 'SELECT COUNT(*) AS total FROM participants'));
    }

    /**
     * @param array<int,mixed> $params
     */
    private function countRows(SimplePdo $db, string $sql, array $params = []): int
    {
        $row = $db->fetchRow($sql, $params);
        if ($row === null || !isset($row['total'])) {
            throw new RuntimeException('Expected count row was not found.');
        }

        return (int) $row['total'];
    }

    private function createGameDatabase(): SimplePdo
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
                balance INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                public_token TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )'
        );

        return $db;
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
            ->onlyMethods(['get'])
            ->addMethods(['json', 'request'])
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
