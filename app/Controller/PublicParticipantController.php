<?php

declare(strict_types=1);

namespace App\Controller;

use flight\database\SimplePdo;
use flight\Engine;

final class PublicParticipantController
{
    /** @var Engine */
    private $app;

    /** @var SimplePdo */
    private $db;

    /**
     * @param Engine $app
     */
    public function __construct(Engine $app, SimplePdo $db)
    {
        $this->app = $app;
        $this->db = $db;
    }

    public function show(string $token): void
    {
        $participant = $this->findParticipantByToken($token);
        if ($participant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }

        $this->app->json($this->serializeParticipant($participant), 200);
    }

    public function history(string $token): void
    {
        $participant = $this->findParticipantByToken($token);
        if ($participant === null) {
            $this->error('not_found', 'Participant not found.', 404);
            return;
        }

        $participantId = (int) $participant['id'];
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
             WHERE t.game_id = ?
               AND (t.from_participant_id = ? OR t.to_participant_id = ?)
             ORDER BY t.id DESC',
            [(int) $participant['game_id'], $participantId, $participantId]
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->serializeTransfer($row, $participantId);
        }

        $this->app->json(['data' => $data], 200);
    }

    /**
     * @return \flight\util\Collection|null
     */
    private function findParticipantByToken(string $token)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32,64}$/', $token)) {
            return null;
        }

        return $this->db->fetchRow(
            'SELECT
                p.id,
                p.game_id,
                g.name AS game_name,
                p.name,
                p.balance,
                p.status,
                p.created_at,
                p.updated_at
             FROM participants p
             INNER JOIN games g ON g.id = p.game_id
             WHERE p.public_token = ?
               AND p.deleted_at IS NULL
               AND g.deleted_at IS NULL',
            [$token]
        );
    }

    /**
     * @param mixed $participant
     * @return array<string,mixed>
     */
    private function serializeParticipant($participant): array
    {
        return [
            'id' => (int) $participant['id'],
            'game_id' => (int) $participant['game_id'],
            'game_name' => (string) $participant['game_name'],
            'name' => (string) $participant['name'],
            'balance' => (int) $participant['balance'],
            'status' => (string) $participant['status'],
            'created_at' => $participant['created_at'],
            'updated_at' => $participant['updated_at'],
        ];
    }

    /**
     * @param mixed $row
     * @return array<string,mixed>
     */
    private function serializeTransfer($row, int $participantId): array
    {
        $fromParticipantId = (int) $row['from_participant_id'];

        return [
            'id' => (int) $row['id'],
            'game_id' => (int) $row['game_id'],
            'from' => [
                'id' => $fromParticipantId,
                'name' => (string) $row['from_participant_name'],
            ],
            'to' => [
                'id' => (int) $row['to_participant_id'],
                'name' => (string) $row['to_participant_name'],
            ],
            'amount' => (int) $row['amount'],
            'direction' => $fromParticipantId === $participantId ? 'out' : 'in',
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
