ALTER TABLE games ADD COLUMN user_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS idx_games_user_id
    ON games (user_id);
