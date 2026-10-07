ALTER TABLE participants
    ADD COLUMN balance INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS participant_transfers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    from_participant_id INTEGER NOT NULL,
    to_participant_id INTEGER NOT NULL,
    amount INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE,
    FOREIGN KEY (from_participant_id) REFERENCES participants (id),
    FOREIGN KEY (to_participant_id) REFERENCES participants (id)
);

CREATE INDEX IF NOT EXISTS idx_participant_transfers_game_id
    ON participant_transfers (game_id);

CREATE INDEX IF NOT EXISTS idx_participant_transfers_from_participant_id
    ON participant_transfers (from_participant_id);

CREATE INDEX IF NOT EXISTS idx_participant_transfers_to_participant_id
    ON participant_transfers (to_participant_id);
