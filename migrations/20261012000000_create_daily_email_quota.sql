CREATE TABLE IF NOT EXISTS daily_email_quota (
    quota_date TEXT PRIMARY KEY,
    sent_count INTEGER NOT NULL DEFAULT 0
);
