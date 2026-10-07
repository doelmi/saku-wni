CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    ended_at TEXT NULL,
    deleted_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_games_deleted_at
    ON games (deleted_at);

CREATE INDEX IF NOT EXISTS idx_games_status
    ON games (status);
