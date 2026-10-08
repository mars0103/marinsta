-- Rode isso se o seu banco já existia antes do suporte a delay.

ALTER TABLE automations
    ADD COLUMN delay_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER target_media_id;

ALTER TABLE automation_logs
    ADD COLUMN scheduled_for DATETIME NULL AFTER dm_sent;

CREATE TABLE IF NOT EXISTS scheduled_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_id INT UNSIGNED NOT NULL,
    ig_account_id INT UNSIGNED NOT NULL,
    media_id VARCHAR(64) NULL,
    ig_comment_id VARCHAR(64) NOT NULL,
    commenter_username VARCHAR(255) NULL,
    comment_text TEXT NULL,
    run_at DATETIME NOT NULL,
    executed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_scheduled_comment (ig_comment_id),
    CONSTRAINT fk_scheduled_actions_automation
        FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
    CONSTRAINT fk_scheduled_actions_ig_account
        FOREIGN KEY (ig_account_id) REFERENCES ig_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
