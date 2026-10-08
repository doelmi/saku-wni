ALTER TABLE participants
    ADD COLUMN public_token TEXT NULL;

UPDATE participants
SET public_token = lower(hex(randomblob(32)))
WHERE public_token IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_participants_public_token
    ON participants (public_token);
