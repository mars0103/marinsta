-- Rode isso se o seu banco já existia antes da busca automática de leads no CRM.

CREATE TABLE IF NOT EXISTS crm_search_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_account_id INT UNSIGNED NOT NULL,
    status ENUM('fila', 'buscando', 'concluido', 'erro') NOT NULL DEFAULT 'fila',
    requested_count TINYINT UNSIGNED NOT NULL DEFAULT 5,
    found_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_search_jobs_ig_account
        FOREIGN KEY (ig_account_id) REFERENCES ig_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
