<?php

declare(strict_types=1);

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Saku WNI API',
        'description' => 'API pendamping permainan board game dan autentikasi OTP email.',
        'version' => '1.0.0',
    ],
    'tags' => [
        ['name' => 'Authentication', 'description' => 'Passwordless login dengan OTP email.'],
        ['name' => 'Games', 'description' => 'Manajemen permainan board game.'],
    ],
    'paths' => [
        '/api/auth/request-otp' => [
            'post' => [
                'tags' => ['Authentication'],
                'summary' => 'Meminta OTP login ke email',
                'operationId' => 'requestOtp',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/RequestOtpRequest'],
                            'example' => ['email' => 'user@example.com'],
                        ],
                    ],
                ],
                'responses' => [
                    '202' => [
                        'description' => 'OTP berhasil dibuat dan dikirim.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/RequestOtpResponse'],
                            ],
                        ],
                    ],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '429' => ['$ref' => '#/components/responses/TooManyRequests'],
                    '503' => ['$ref' => '#/components/responses/EmailDeliveryFailed'],
                ],
            ],
        ],
        '/api/auth/verify-otp' => [
            'post' => [
                'tags' => ['Authentication'],
                'summary' => 'Memverifikasi OTP dan mendapatkan access token',
                'operationId' => 'verifyOtp',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/VerifyOtpRequest'],
                            'example' => ['challenge_id' => 1, 'otp' => '123456'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OTP valid.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/TokenResponse'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/InvalidOtp'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/api/auth/logout' => [
            'post' => [
                'tags' => ['Authentication'],
                'summary' => 'Logout dan mencabut access token aktif',
                'operationId' => 'logout',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Token berhasil dicabut.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ],
        '/api/games' => [
            'get' => [
                'tags' => ['Games'],
                'summary' => 'Mendapatkan daftar game yang belum dihapus',
                'operationId' => 'listGames',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Daftar game.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['data'],
                                    'properties' => [
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Game'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
            'post' => [
                'tags' => ['Games'],
                'summary' => 'Membuat game baru',
                'operationId' => 'createGame',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/GameNameRequest'],
                            'example' => ['name' => 'Friday Monopoly'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Game berhasil dibuat.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Game'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/api/games/{id}' => [
            'parameters' => [['$ref' => '#/components/parameters/GameId']],
            'patch' => [
                'tags' => ['Games'],
                'summary' => 'Mengubah nama game',
                'operationId' => 'updateGame',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/GameNameRequest'],
                            'example' => ['name' => 'Saturday Monopoly'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Game berhasil diperbarui.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Game'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Games'],
                'summary' => 'Soft delete game',
                'operationId' => 'deleteGame',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Game berhasil di-soft-delete.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
        '/api/games/{id}/close' => [
            'parameters' => [['$ref' => '#/components/parameters/GameId']],
            'patch' => [
                'tags' => ['Games'],
                'summary' => 'Menutup game dan mengisi ended_at',
                'operationId' => 'closeGame',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Game berhasil ditutup.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Game'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '409' => ['$ref' => '#/components/responses/GameAlreadyClosed'],
                ],
            ],
        ],
        '/api/games/{game_id}/participants' => [
            'parameters' => [['$ref' => '#/components/parameters/ParticipantGameId']],
            'get' => [
                'tags' => ['Games'],
                'summary' => 'Mendapatkan peserta dalam game',
                'operationId' => 'listParticipants',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Daftar peserta aktif.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/Participant'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'post' => [
                'tags' => ['Games'],
                'summary' => 'Menambahkan peserta ke game',
                'operationId' => 'createParticipant',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ParticipantNameRequest'],
                            'example' => ['name' => 'Budi'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Peserta berhasil ditambahkan.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Participant'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
        '/api/games/{game_id}/participants/{participant_id}' => [
            'parameters' => [
                ['$ref' => '#/components/parameters/ParticipantGameId'],
                [
                    'name' => 'participant_id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'patch' => [
                'tags' => ['Games'],
                'summary' => 'Mengubah nama peserta',
                'operationId' => 'updateParticipant',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ParticipantNameRequest'],
                            'example' => ['name' => 'Budi Santoso'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Peserta berhasil diperbarui.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Participant'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Games'],
                'summary' => 'Soft delete peserta',
                'operationId' => 'deleteParticipant',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Peserta berhasil dihapus.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ],
        '/api/games/{game_id}/transfers' => [
            'parameters' => [['$ref' => '#/components/parameters/ParticipantGameId']],
            'get' => [
                'tags' => ['Games'],
                'summary' => 'Mendapatkan history keluar masuk uang',
                'operationId' => 'listTransferHistory',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    [
                        'name' => 'participant_id',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer', 'minimum' => 1],
                        'description' => 'Filter history untuk peserta tertentu, baik sebagai pengirim maupun penerima.',
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Daftar history transfer.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['data'],
                                    'properties' => [
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/TransferHistory'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'post' => [
                'tags' => ['Games'],
                'summary' => 'Transfer uang antar peserta',
                'operationId' => 'transferParticipantMoney',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ParticipantTransferRequest'],
                            'example' => [
                                'from_participant_id' => 1,
                                'to_participant_id' => 2,
                                'amount' => 500,
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Transfer berhasil.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ParticipantTransfer'],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ],
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'opaque access token',
            ],
        ],
        'parameters' => [
            'GameId' => [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            'ParticipantGameId' => [
                'name' => 'game_id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
        ],
        'schemas' => [
            'RequestOtpRequest' => [
                'type' => 'object',
                'required' => ['email'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                ],
            ],
            'RequestOtpResponse' => [
                'type' => 'object',
                'required' => ['message', 'challenge_id', 'expires_in'],
                'properties' => [
                    'message' => ['type' => 'string'],
                    'challenge_id' => ['type' => 'integer'],
                    'expires_in' => ['type' => 'integer', 'description' => 'Durasi dalam detik.'],
                ],
            ],
            'VerifyOtpRequest' => [
                'type' => 'object',
                'required' => ['challenge_id', 'otp'],
                'properties' => [
                    'challenge_id' => ['type' => 'integer', 'minimum' => 1],
                    'otp' => ['type' => 'string', 'pattern' => '^[0-9]{6}$'],
                ],
            ],
            'TokenResponse' => [
                'type' => 'object',
                'required' => ['access_token', 'token_type', 'expires_in'],
                'properties' => [
                    'access_token' => ['type' => 'string'],
                    'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                    'expires_in' => ['type' => 'integer', 'description' => 'Durasi dalam detik.'],
                ],
            ],
            'GameNameRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                ],
            ],
            'Game' => [
                'type' => 'object',
                'required' => ['id', 'name', 'created_at', 'status', 'ended_at', 'deleted_at'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'closed']],
                    'ended_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'deleted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'ParticipantNameRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                ],
            ],
            'ParticipantTransferRequest' => [
                'type' => 'object',
                'required' => ['from_participant_id', 'to_participant_id', 'amount'],
                'properties' => [
                    'from_participant_id' => ['type' => 'integer', 'minimum' => 1],
                    'to_participant_id' => ['type' => 'integer', 'minimum' => 1],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'ParticipantTransfer' => [
                'type' => 'object',
                'required' => ['game_id', 'amount', 'data', 'transferred_at'],
                'properties' => [
                    'game_id' => ['type' => 'integer'],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'data' => [
                        'type' => 'object',
                        'required' => ['from', 'to'],
                        'properties' => [
                            'from' => ['$ref' => '#/components/schemas/ParticipantBalance'],
                            'to' => ['$ref' => '#/components/schemas/ParticipantBalance'],
                        ],
                    ],
                    'transferred_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ParticipantBalance' => [
                'type' => 'object',
                'required' => ['id', 'name', 'balance'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'balance' => ['type' => 'integer', 'minimum' => 0],
                ],
            ],
            'TransferHistory' => [
                'type' => 'object',
                'required' => ['id', 'game_id', 'from', 'to', 'amount', 'direction', 'created_at'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'game_id' => ['type' => 'integer'],
                    'from' => ['$ref' => '#/components/schemas/TransferParticipant'],
                    'to' => ['$ref' => '#/components/schemas/TransferParticipant'],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'direction' => [
                        'type' => 'string',
                        'enum' => ['in', 'out'],
                        'nullable' => true,
                        'description' => 'Bernilai null saat tidak memakai filter participant_id.',
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TransferParticipant' => [
                'type' => 'object',
                'required' => ['id', 'name'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ],
            'Participant' => [
                'type' => 'object',
                'required' => [
                    'id',
                    'game_id',
                    'name',
                    'balance',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'game_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'balance' => ['type' => 'integer', 'minimum' => 0],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'deleted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'Error' => [
                'type' => 'object',
                'required' => ['error'],
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'required' => ['code', 'message'],
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'retry_after' => ['type' => 'integer', 'nullable' => true],
                        ],
                    ],
                ],
            ],
            'MessageResponse' => [
                'type' => 'object',
                'required' => ['message'],
                'properties' => ['message' => ['type' => 'string']],
            ],
        ],
        'responses' => [
            'Unauthorized' => [
                'description' => 'Bearer token tidak ada, tidak valid, atau sudah kedaluwarsa.',
                'headers' => [
                    'WWW-Authenticate' => [
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'ValidationError' => [
                'description' => 'Input tidak valid.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'TooManyRequests' => [
                'description' => 'Terlalu banyak request OTP.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'EmailDeliveryFailed' => [
                'description' => 'Email OTP gagal dikirim.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'InvalidOtp' => [
                'description' => 'OTP tidak valid atau sudah kedaluwarsa.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'NotFound' => [
                'description' => 'Game tidak ditemukan.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'GameAlreadyClosed' => [
                'description' => 'Game sudah ditutup.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
        ],
    ],
];
