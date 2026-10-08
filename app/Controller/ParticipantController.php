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

final class ParticipantController
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

    /**
     * @param string|int $gameId
     */
    public function index($gameId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $participants = (new Participant($this->db))
            ->eq('game_id', (int) $gameId)
            ->isNull('deleted_at')
            ->orderByColumn('id', 'ASC')
            ->findAll();

        $data = [];
        foreach ($participants as $participant) {
            if ($participant instanceof Participant) {
                $data[] = $this->serializeParticipant($participant);
            }
        }

        $this->app->json(['data' => $data], 200);
    }

    /**
     * @param string|int $gameId
     */
    public function create($gameId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $name = $this->input('name');
        if (!is_string($name)) {
            $this->error('validation_error', 'A participant name is required.', 422);
            return;
        }

        try {
            $name = $this->validateUserParticipantName($name);
            $now = $this->now();
            $participant = new Participant($this->db);
            $participant->copyFrom([
                'game_id' => (int) $gameId,
                'name' => $name,
                'status' => Participant::STATUS_ACTIVE,
                'public_token' => Participant::generatePublicToken(),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ])->insert();

            $this->app->json($this->serializeParticipant($participant), 201);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        }
    }

    /**
     * @param string|int $gameId
     * @param string|int $participantId
     */
    public function updateStatus($gameId, $participantId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $participant = $this->findActiveParticipant($gameId, $participantId);
        if ($participant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }

        $status = $this->input('status');
        if (!is_string($status)) {
            $this->error('validation_error', 'Participant status is required.', 422);
            return;
        }

        try {
            $status = $this->validateStatus($status);
            if ($status === Participant::STATUS_BANKRUPT) {
                $this->bankruptParticipant($gameId, $participantId, $participant);
            } else {
                $participant->copyFrom([
                    'status' => $status,
                    'updated_at' => $this->now(),
                ])->update();
            }

            $updatedParticipant = $this->findActiveParticipant($gameId, $participantId);
            if ($updatedParticipant === null) {
                $this->error('not_found', 'Participant not found.', 404);
                return;
            }

            $this->app->json($this->serializeParticipant($updatedParticipant), 200);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        }
    }

    /**
     * @param string|int $gameId
     * @param string|int $participantId
     */
    public function update($gameId, $participantId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $participant = $this->findActiveParticipant($gameId, $participantId);
        if ($participant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }
        if ($this->isCountryParticipant($participant)) {
            $this->error('validation_error', 'Negara participant cannot be renamed.', 422);
            return;
        }

        $name = $this->input('name');
        if (!is_string($name)) {
            $this->error('validation_error', 'A participant name is required.', 422);
            return;
        }

        try {
            $participant->copyFrom([
                'name' => $this->validateUserParticipantName($name),
                'updated_at' => $this->now(),
            ])->update();

            $this->app->json($this->serializeParticipant($participant), 200);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
        }
    }

    /**
     * @param string|int $gameId
     * @param string|int $participantId
     */
    public function delete($gameId, $participantId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $participant = $this->findActiveParticipant($gameId, $participantId);
        if ($participant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }
        if ($this->isCountryParticipant($participant)) {
            $this->error('validation_error', 'Negara participant cannot be deleted.', 422);
            return;
        }

        $now = $this->now();
        $participant->copyFrom([
            'deleted_at' => $now,
            'updated_at' => $now,
        ])->update();

        $this->app->json(['message' => 'Participant deleted.'], 200);
    }

    /**
     * @param string|int $gameId
     */
    private function findOwnedGame($gameId): ?Game
    {
        if (!$this->validId($gameId)) {
            return null;
        }

        $game = (new Game($this->db))
            ->eq('id', (int) $gameId)
            ->eq('user_id', $this->currentUserId())
            ->isNull('deleted_at')
            ->find();

        return $game instanceof Game && $game->isHydrated() ? $game : null;
    }

    /**
     * @param string|int $gameId
     * @param string|int $participantId
     */
    private function findActiveParticipant($gameId, $participantId): ?Participant
    {
        if (!$this->validId($gameId) || !$this->validId($participantId)) {
            return null;
        }

        $participant = (new Participant($this->db))
            ->eq('id', (int) $participantId)
            ->eq('game_id', (int) $gameId)
            ->isNull('deleted_at')
            ->find();

        return $participant instanceof Participant && $participant->isHydrated()
            ? $participant
            : null;
    }

    /**
     * @param mixed $id
     */
    private function validId($id): bool
    {
        return (is_int($id) || is_string($id))
            && ctype_digit((string) $id)
            && (int) $id > 0;
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException(
                'Participant name must be between 1 and 255 characters.'
            );
        }

        return $name;
    }

    private function validateUserParticipantName(string $name): string
    {
        $name = $this->validateName($name);
        if (strcasecmp($name, Participant::DEFAULT_NAME) === 0) {
            throw new InvalidArgumentException('Participant name cannot be Negara.');
        }

        return $name;
    }

    private function validateStatus(string $status): string
    {
        $status = trim($status);
        if (!in_array($status, [
            Participant::STATUS_ACTIVE,
            Participant::STATUS_BANKRUPT,
        ], true)) {
            throw new InvalidArgumentException('Participant status must be active or bankrupt.');
        }

        return $status;
    }

    /**
     * @param string|int $gameId
     * @param string|int $participantId
     */
    private function bankruptParticipant($gameId, $participantId, Participant $participant): void
    {
        if ($this->isCountryParticipant($participant)) {
            throw new InvalidArgumentException('Negara cannot be declared bankrupt.');
        }

        $now = $this->now();
        $this->db->transaction(function () use ($gameId, $participantId, $participant, $now): void {
            if ((string) $participant->status === Participant::STATUS_BANKRUPT) {
                $participant->copyFrom([
                    'status' => Participant::STATUS_BANKRUPT,
                    'updated_at' => $now,
                ])->update();
                return;
            }

            $country = (new Participant($this->db))
                ->eq('game_id', (int) $gameId)
                ->eq('name', Participant::DEFAULT_NAME)
                ->isNull('deleted_at')
                ->orderByColumn('id', 'ASC')
                ->find();

            if (!$country instanceof Participant || !$country->isHydrated()) {
                throw new InvalidArgumentException('Negara participant not found.');
            }

            $balance = (int) $participant->balance;
            $updated = $this->db->runQuery(
                'UPDATE participants
                 SET status = ?, balance = 0, updated_at = ?
                 WHERE id = ? AND game_id = ? AND deleted_at IS NULL',
                [
                    Participant::STATUS_BANKRUPT,
                    $now,
                    (int) $participantId,
                    (int) $gameId,
                ]
            );

            if ($updated->rowCount() !== 1) {
                throw new InvalidArgumentException('Participant status could not be updated.');
            }

            if ($balance < 1) {
                return;
            }

            $this->db->runQuery(
                'UPDATE participants
                 SET balance = balance + ?, updated_at = ?
                 WHERE id = ? AND game_id = ? AND deleted_at IS NULL',
                [
                    $balance,
                    $now,
                    (int) $country->id,
                    (int) $gameId,
                ]
            );

            $this->db->runQuery(
                'INSERT INTO participant_transfers
                    (game_id, from_participant_id, to_participant_id, amount, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    (int) $gameId,
                    (int) $participantId,
                    (int) $country->id,
                    $balance,
                    $now,
                ]
            );
        });
    }

    private function currentUserId(): int
    {
        return (int) $this->app->get('auth.user_id');
    }

    private function isCountryParticipant(Participant $participant): bool
    {
        return strcasecmp((string) $participant->name, Participant::DEFAULT_NAME) === 0;
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
    private function serializeParticipant(Participant $participant): array
    {
        return [
            'id' => (int) $participant->id,
            'game_id' => (int) $participant->game_id,
            'name' => (string) $participant->name,
            'balance' => (int) $participant->balance,
            'status' => (string) $participant->status,
            'public_token' => $participant->public_token,
            'created_at' => $participant->created_at,
            'updated_at' => $participant->updated_at,
            'deleted_at' => $participant->deleted_at,
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
