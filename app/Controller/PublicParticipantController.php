<?php

declare(strict_types=1);

namespace App\Controller;

use App\Utils\Config;
use flight\database\SimplePdo;
use flight\Engine;

final class PublicParticipantController
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

    public function show(string $token): void
    {
        $participant = $this->findParticipantByToken($token);
        if ($participant === null) {
            $this->error('not_found', 'Peserta tidak ditemukan.', 404);
            return;
        }

        $this->app->json($this->serializeParticipant($participant), 200);
    }

    public function history(string $token): void
    {
        $participant = $this->findParticipantByToken($token);
        if ($participant === null) {
            $this->error('not_found', 'Peserta tidak ditemukan.', 404);
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
     * Stream participant changes for EventSource clients.
     *
     * The stream intentionally has a finite lifetime. This works better with
     * PHP-FPM and shared hosting, while EventSource reconnects automatically.
     */
    public function stream(string $token): void
    {
        $maxDuration = $this->configInteger('sse.max_duration', 55, 1, 300);
        $pollInterval = $this->configInteger('sse.poll_interval', 2, 1, 30);
        $retryAfter = $this->configInteger('sse.retry_after', 3000, 1000, 60000);
        $startedAt = microtime(true);
        $lastFingerprint = null;

        $this->writeSse('retry', (string) $retryAfter);

        while ((microtime(true) - $startedAt) < $maxDuration) {
            if (connection_aborted() === 1) {
                return;
            }

            $participant = $this->findParticipantByToken($token);
            if ($participant === null) {
                $this->writeSse('error', [
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'Peserta tidak ditemukan.',
                    ],
                ]);
                return;
            }

            $data = $this->serializeParticipant($participant);
            $fingerprint = implode('|', [
                (string) $data['id'],
                (string) $data['name'],
                (string) $data['balance'],
                (string) $data['status'],
                (string) $data['updated_at'],
            ]);

            if ($fingerprint !== $lastFingerprint) {
                $this->writeSse('participant.updated', $data);
                $lastFingerprint = $fingerprint;
            } else {
                // Keep proxies and browser connections alive without sending
                // a second participant payload when nothing changed.
                $this->writeSseComment('heartbeat');
            }

            sleep($pollInterval);
        }
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

    /**
     * @param mixed $data
     */
    private function writeSse(string $event, $data): void
    {
        $encoded = is_string($data)
            ? $data
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            $encoded = '{}';
        }

        echo 'event: ' . $event . "\n";
        foreach (explode("\n", $encoded) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        $this->flushOutput();
    }

    private function writeSseComment(string $comment): void
    {
        echo ': ' . $comment . "\n\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function configInteger(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = (int) $this->config->get($key, $default);

        return max($minimum, min($value, $maximum));
    }
}
