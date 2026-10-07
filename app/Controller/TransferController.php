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

final class TransferController
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

        $participantId = $this->query('participant_id');
        if ($participantId !== null && !$this->validId($participantId)) {
            $this->error('validation_error', 'Participant ID must be a positive integer.', 422);
            return;
        }

        if ($participantId !== null && $this->findParticipant($gameId, $participantId) === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }

        $params = [(int) $gameId];
        $filterSql = '';
        if ($participantId !== null) {
            $filterSql = ' AND (t.from_participant_id = ? OR t.to_participant_id = ?)';
            $params[] = (int) $participantId;
            $params[] = (int) $participantId;
        }

        $rows = $this->db->fetchAll(
            'SELECT
                t.id,
                t.game_id,
                t.from_participant_id,
                from_participant.name AS from_participant_name,
                t.to_participant_id,
                to_participant.name AS to_participant_name,
                t.amount,
                t.created_at
             FROM participant_transfers t
             INNER JOIN participants from_participant
                ON from_participant.id = t.from_participant_id
             INNER JOIN participants to_participant
                ON to_participant.id = t.to_participant_id
             WHERE t.game_id = ?' . $filterSql . '
             ORDER BY t.id DESC',
            $params
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->serializeTransfer($row, $participantId === null ? null : (int) $participantId);
        }

        $this->app->json(['data' => $data], 200);
    }

    /**
     * Transfer money between two active participants in the same game.
     *
     * @param string|int $gameId
     */
    public function transfer($gameId): void
    {
        if ($this->findOwnedGame($gameId) === null) {
            $this->error('not_found', 'Game not found.', 404);
            return;
        }

        $fromParticipantId = $this->input('from_participant_id');
        $toParticipantId = $this->input('to_participant_id');
        $amount = $this->input('amount');

        if (
            !$this->validId($fromParticipantId)
            || !$this->validId($toParticipantId)
            || (int) $fromParticipantId === (int) $toParticipantId
        ) {
            $this->error(
                'validation_error',
                'Source and destination participants must be different valid IDs.',
                422
            );
            return;
        }

        try {
            $amount = $this->validateAmount($amount);
        } catch (InvalidArgumentException $e) {
            $this->error('validation_error', $e->getMessage(), 422);
            return;
        }

        $fromParticipant = $this->findActiveParticipant($gameId, $fromParticipantId);
        $toParticipant = $this->findActiveParticipant($gameId, $toParticipantId);
        if ($fromParticipant === null || $toParticipant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }

        $now = $this->now();
        try {
            $result = $this->db->transaction(function () use (
                $gameId,
                $fromParticipantId,
                $toParticipantId,
                $amount,
                $now
            ): array {
                $debit = $this->db->runQuery(
                    'UPDATE participants
                     SET balance = balance - ?, updated_at = ?
                     WHERE id = ? AND game_id = ? AND deleted_at IS NULL AND balance >= ?',
                    [
                        $amount,
                        $now,
                        (int) $fromParticipantId,
                        (int) $gameId,
                        $amount,
                    ]
                );

                if ($debit->rowCount() !== 1) {
                    throw new InvalidArgumentException('Insufficient participant balance.');
                }

                $credit = $this->db->runQuery(
                    'UPDATE participants
                     SET balance = balance + ?, updated_at = ?
                     WHERE id = ? AND game_id = ? AND deleted_at IS NULL',
                    [
                        $amount,
                        $now,
                        (int) $toParticipantId,
                        (int) $gameId,
                    ]
                );

                if ($credit->rowCount() !== 1) {
                    throw new InvalidArgumentException('Participant not found.');
                }

                $this->db->runQuery(
                    'INSERT INTO participant_transfers
                        (game_id, from_participant_id, to_participant_id, amount, created_at)
                     VALUES (?, ?, ?, ?, ?)',
                    [
                        (int) $gameId,
                        (int) $fromParticipantId,
                        (int) $toParticipantId,
                        $amount,
                        $now,
                    ]
                );

                $from = $this->db->fetchRow(
                    'SELECT id, name, balance FROM participants WHERE id = ?',
                    [(int) $fromParticipantId]
                );
                $to = $this->db->fetchRow(
                    'SELECT id, name, balance FROM participants WHERE id = ?',
                    [(int) $toParticipantId]
                );

                if ($from === null || $to === null) {
                    throw new InvalidArgumentException('Participant not found.');
                }

                return [
                    'from' => [
                        'id' => (int) $from['id'],
                        'name' => (string) $from['name'],
                        'balance' => (int) $from['balance'],
                    ],
                    'to' => [
                        'id' => (int) $to['id'],
                        'name' => (string) $to['name'],
                        'balance' => (int) $to['balance'],
                    ],
                ];
            });

            $this->app->json([
                'game_id' => (int) $gameId,
                'amount' => $amount,
                'data' => $result,
                'transferred_at' => $now,
            ], 200);
        } catch (InvalidArgumentException $e) {
            $this->error('transfer_failed', $e->getMessage(), 422);
        }
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
    private function findParticipant($gameId, $participantId): ?Participant
    {
        if (!$this->validId($gameId) || !$this->validId($participantId)) {
            return null;
        }

        $participant = (new Participant($this->db))
            ->eq('id', (int) $participantId)
            ->eq('game_id', (int) $gameId)
            ->find();

        return $participant instanceof Participant && $participant->isHydrated()
            ? $participant
            : null;
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
     * @return mixed
     */
    private function query(string $key)
    {
        return $this->app->request()->query->{$key};
    }

    /**
     * @param mixed $amount
     */
    private function validateAmount($amount): int
    {
        if (
            (!is_int($amount) && !is_string($amount))
            || !ctype_digit((string) $amount)
            || (int) $amount < 1
            || strlen((string) $amount) > strlen((string) PHP_INT_MAX)
        ) {
            throw new InvalidArgumentException('Transfer amount must be a positive integer.');
        }

        return (int) $amount;
    }

    private function now(): string
    {
        $timezone = (string) $this->config->get('app.timezone', date_default_timezone_get());

        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d H:i:s');
    }

    /**
     * @param mixed $row
     * @return array<string,mixed>
     */
    private function serializeTransfer($row, ?int $participantId): array
    {
        $fromParticipantId = (int) $row['from_participant_id'];
        $toParticipantId = (int) $row['to_participant_id'];

        $direction = null;
        if ($participantId !== null) {
            $direction = $fromParticipantId === $participantId ? 'out' : 'in';
        }

        return [
            'id' => (int) $row['id'],
            'game_id' => (int) $row['game_id'],
            'from' => [
                'id' => $fromParticipantId,
                'name' => (string) $row['from_participant_name'],
            ],
            'to' => [
                'id' => $toParticipantId,
                'name' => (string) $row['to_participant_name'],
            ],
            'amount' => (int) $row['amount'],
            'direction' => $direction,
            'created_at' => $row['created_at'],
        ];
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
