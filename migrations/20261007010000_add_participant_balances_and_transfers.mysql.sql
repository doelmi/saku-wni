ALTER TABLE `participants`
    ADD COLUMN `balance` BIGINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `participant_transfers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` INT UNSIGNED NOT NULL,
    `from_participant_id` INT UNSIGNED NOT NULL,
    `to_participant_id` INT UNSIGNED NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_participant_transfers_game_id` (`game_id`),
    KEY `idx_participant_transfers_from_participant_id` (`from_participant_id`),
    KEY `idx_participant_transfers_to_participant_id` (`to_participant_id`),
    CONSTRAINT `fk_participant_transfers_game`
        FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_participant_transfers_from`
        FOREIGN KEY (`from_participant_id`) REFERENCES `participants` (`id`),
    CONSTRAINT `fk_participant_transfers_to`
        FOREIGN KEY (`to_participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
