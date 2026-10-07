ALTER TABLE `games`
    ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`,
    ADD KEY `idx_games_user_id` (`user_id`);
