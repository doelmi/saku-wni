CREATE TABLE IF NOT EXISTS `daily_email_quota` (
    `quota_date` DATE NOT NULL,
    `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`quota_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
