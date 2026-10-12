<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Game;
use App\Model\Participant;
use App\Utils\Config;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use flight\database\SimplePdo;
use flight\Engine;
use PDO;

final class GameController
{
    private const GLOBAL_DAILY_LIMIT_CODE = 1;

    /** @var Engine */
    private $app;

    /** @var SimplePdo */
    private $db;

    /** @var Config */
    private $config;

    /**
     * @param Engine $app
     */
    public function __construct(Engine $app, SimplePdo $db, Config $config)
    {
        $this->app = $app;
        $this->db = $db;
        $this->config = $config;
    }

    public function index(): void
    {
        $games = (new Game($this->db))
            ->eq('user_id', $this->currentUserId())
            ->isNull('deleted_at')
            ->orderByColumn('id', 'DESC')
            ->findAll();

        $data = [];
        foreach ($games as $game) {
            if ($game instanceof Game) {
                $data[] = $this->serializeGame($game);
            }
        }

        $this->app->json(['data' => $data], 200);
    }

    public function create(): void
    {
        $name = $this->input('name');
        if (!is_string($name)) {
            $this->error('validation_error', 'Nama game wajib diisi.', 422);
            return;
        }

        try {
            $gameName = $this->validateName($name);
            $now = $this->now();
            $userId = $this->currentUserId();
            $userLimit = max(1, (int) $this->config->get('games.daily_create_limit', 5));
            $globalLimit = max(1, (int) $this->config->get('games.global_daily_create_limit', 100));

            $game = $this->db->transaction(function () use (
                $gameName, $now, $userId, $userLimit, $globalLimit
            ): Game {
                if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    // Semua pembuatan game menunggu kunci yang sama sebelum menghitung kuota.
                    $this->db->runQuery('SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE');
                }

                $timezone = new DateTimeZone((string) $this->config->get(
                    'app.timezone',
                    date_default_timezone_get()
                ));
                $today = new DateTimeImmutable($now, $timezone);
                $start = $today->format('Y-m-d 00:00:00');
                $end = $today->modify('tomorrow')->format('Y-m-d 00:00:00');
                $count = $this->db->fetchRow(
                    'SELECT COUNT(*) AS total FROM games
                     WHERE user_id = ? AND created_at >= ? AND created_at < ?',
                    [$userId, $start, $end]
                );

                if ((int) ($count['total'] ?? 0) >= $userLimit) {
                    throw new DomainException('Jatah bikin game hari ini sudah habis. Coba lagi besok, ya!');
                }

                $globalCount = $this->db->fetchRow(
                    'SELECT COUNT(*) AS total FROM games
                     WHERE created_at >= ? AND created_at < ?',
                    [$start, $end]
                );
                if ((int) ($globalCount['total'] ?? 0) >= $globalLimit) {
                    throw new DomainException(
                        'Jatah game untuk semua pemain hari ini sudah habis. Coba lagi besok, ya!',
                        self::GLOBAL_DAILY_LIMIT_CODE
                    );
                }

                $game = new Game($this->db);
                $game->copyFrom([
                    'user_id' => $userId,
                    'name' => $gameName,
                    'created_at' => $now,
                    'status' => Game::STATUS_ACTIVE,
                    'ended_at' => null,
                    'deleted_at' => null,
                ])->insert();

                (new Participant($this->db))->copyFrom([
                    'game_id' => (int) $game->id,
                    'name' => Participant::DEFAULT_NAME,
                    'balance' => 1_000_000_000_000_000,
                    'status' => Participant::STATUS_ACTIVE,
                    'public_token' => Participant::generatePublicToken(),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ])->insert();

                return $game;
            });

            $this->app->json($this->serializeGame($game), 201);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        } catch (DomainException $e) {
            $code = $e->getCode() === self::GLOBAL_DAILY_LIMIT_CODE
                ? 'game_global_daily_limit_reached' : 'game_daily_limit_reached';
            $this->error($code, $e->getMessage(), 429);
        }
    }

    /**
     * @param string|int $id
     */
    public function update($id): void
    {
        $game = $this->findActiveGame($id);
        if ($game === null) {
            $this->error('not_found', 'Game tidak ditemukan.', 404);
            return;
        }

        $name = $this->input('name');
        if (!is_string($name)) {
            $this->error('validation_error', 'Nama game wajib diisi.', 422);
            return;
        }

        try {
            $game->updateAttribute('name', $this->validateName($name));
            $this->app->json($this->serializeGame($game), 200);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        }
    }

    /**
     * @param string|int $id
     */
    public function delete($id): void
    {
        $game = $this->findActiveGame($id);
        if ($game === null) {
            $this->error('not_found', 'Game tidak ditemukan.', 404);
            return;
        }

        $game->updateAttribute('deleted_at', $this->now());
        $this->app->json([
            'message' => 'Game berhasil dihapus.',
        ], 200);
    }

    /**
     * Close a game and record its end timestamp.
     *
     * @param string|int $id
     */
    public function close($id): void
    {
        $game = $this->findActiveGame($id);
        if ($game === null) {
            $this->error('not_found', 'Game tidak ditemukan.', 404);
            return;
        }

        if ((string) $game->status === Game::STATUS_CLOSED) {
            $this->error('game_already_closed', 'Game ini sudah ditutup.', 409);
            return;
        }

        $game->copyFrom([
            'status' => Game::STATUS_CLOSED,
            'ended_at' => $this->now(),
        ])->update();

        $this->app->json($this->serializeGame($game), 200);
    }

    /**
     * @param string|int $id
     */
    private function findActiveGame($id): ?Game
    {
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
            return null;
        }

        $game = (new Game($this->db))
            ->eq('user_id', $this->currentUserId())
            ->isNull('deleted_at')
            ->find((int) $id);

        return $game instanceof Game && $game->isHydrated() ? $game : null;
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Nama game harus terdiri dari 1 sampai 255 karakter.');
        }

        return $name;
    }

    private function currentUserId(): int
    {
        return (int) $this->app->get('auth.user_id');
    }

    /**
     * @return mixed
     */
    private function input(string $key)
    {
        return $this->app->request()->data->{$key};
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeGame(Game $game): array
    {
        return [
            'id' => (int) $game->id,
            'name' => (string) $game->name,
            'created_at' => $game->created_at,
            'status' => (string) $game->status,
            'ended_at' => $game->ended_at,
            'deleted_at' => $game->deleted_at,
        ];
    }

    private function now(): string
    {
        $timezone = (string) $this->config->get('app.timezone', date_default_timezone_get());

        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d H:i:s');
    }

    private function error(string $code, string $message, int $status): void
    {
        $this->app->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
