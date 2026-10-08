ALTER TABLE `participants`
    ADD COLUMN `public_token` VARCHAR(64) NULL;

UPDATE `participants`
SET `public_token` = SHA2(RAND(), 256)
WHERE `public_token` IS NULL;

CREATE UNIQUE INDEX `uq_participants_public_token`
    ON `participants` (`public_token`);
