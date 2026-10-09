<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Game;
use App\Model\Participant;
use App\Utils\Config;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use flight\database\SimplePdo;
use flight\Engine;

final class GameController
{
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

            $game = $this->db->transaction(function () use ($gameName, $now, $userId): Game {
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
