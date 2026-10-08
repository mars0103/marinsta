-- Rode este arquivo no phpMyAdmin (aba "Importar" ou "SQL") no banco que você criar no cPanel.

CREATE TABLE IF NOT EXISTS ig_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_user_id VARCHAR(64) NOT NULL,
    username VARCHAR(255) NOT NULL,
    access_token TEXT NOT NULL,
    token_expires_at DATETIME NOT NULL,
    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ig_user_id (ig_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reply_packs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_account_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reply_packs_ig_account
        FOREIGN KEY (ig_account_id) REFERENCES ig_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reply_pack_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pack_id INT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    CONSTRAINT fk_reply_pack_items_pack
        FOREIGN KEY (pack_id) REFERENCES reply_packs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_account_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    keywords TEXT NOT NULL COMMENT 'palavras separadas por vírgula',
    match_type ENUM('any','all') NOT NULL DEFAULT 'any',
    target_scope ENUM('all','next','specific') NOT NULL DEFAULT 'all',
    target_media_id VARCHAR(64) NULL COMMENT 'preenchido quando target_scope = specific, ou resolvido automaticamente quando = next',
    delay_minutes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'espera antes de responder, 0 = imediato',
    reply_text TEXT NOT NULL,
    reply_pack_id INT UNSIGNED NULL,
    send_dm TINYINT(1) NOT NULL DEFAULT 0,
    dm_text TEXT NULL COMMENT 'mensagem enviada por direct para quem comentou (pode conter links)',
    dm_pack_id INT UNSIGNED NULL,
    dm_button_label VARCHAR(20) NULL COMMENT 'texto do botão da DM, se usar botão em vez de link no corpo',
    dm_button_url VARCHAR(500) NULL,
    flow_json LONGTEXT NULL COMMENT 'layout do editor visual (posições/conexões dos nós)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_automations_ig_account
        FOREIGN KEY (ig_account_id) REFERENCES ig_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_automations_reply_pack
        FOREIGN KEY (reply_pack_id) REFERENCES reply_packs(id) ON DELETE SET NULL,
    CONSTRAINT fk_automations_dm_pack
        FOREIGN KEY (dm_pack_id) REFERENCES reply_packs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_id INT UNSIGNED NULL,
    ig_comment_id VARCHAR(64) NOT NULL,
    commenter_username VARCHAR(255) NULL,
    comment_text TEXT NULL,
    matched TINYINT(1) NOT NULL DEFAULT 0,
    reply_sent TINYINT(1) NOT NULL DEFAULT 0,
    dm_sent TINYINT(1) NOT NULL DEFAULT 0,
    scheduled_for DATETIME NULL COMMENT 'quando a resposta com delay vai ser executada',
    error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ig_comment_id (ig_comment_id),
    KEY idx_automation_id (automation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS crm_opportunities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_account_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    segment ENUM('A','B') NOT NULL DEFAULT 'A' COMMENT 'A = indústria/B2B, B = marca/produto premium',
    profile_url VARCHAR(500) NULL COMMENT 'site institucional',
    instagram_url VARCHAR(500) NULL,
    contact_name VARCHAR(255) NULL,
    contact_role VARCHAR(255) NULL,
    source VARCHAR(255) NULL COMMENT 'onde achou esse lead',
    score_valor_alto TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_problema TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_momento TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_decisor TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_investimento TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_total TINYINT UNSIGNED GENERATED ALWAYS AS
        (score_valor_alto + score_problema + score_momento + score_decisor + score_investimento) STORED,
    problem_observed TEXT NULL,
    status ENUM(
        'pesquisado','selecionado','contato_feito','respondeu','qualificado',
        'reuniao_marcada','proposta_enviada','negociacao','ganho','perdido','pausado'
    ) NOT NULL DEFAULT 'pesquisado',
    proposed_value DECIMAL(10,2) NULL,
    received_value DECIMAL(10,2) NULL DEFAULT 0,
    last_interaction_at DATE NULL,
    next_action TEXT NULL,
    next_action_at DATE NULL,
    consent_context TEXT NULL COMMENT 'contexto/consentimento pro primeiro contato',
    outreach_script TEXT NULL,
    loss_reason TEXT NULL,
    responsible VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_crm_company (ig_account_id, company_name),
    CONSTRAINT fk_crm_opportunities_ig_account
        FOREIGN KEY (ig_account_id) REFERENCES ig_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crm_interactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT UNSIGNED NOT NULL,
    type ENUM('nota','contato','resposta','reuniao','proposta','status') NOT NULL DEFAULT 'nota',
    notes TEXT NULL,
    happened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_interactions_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
