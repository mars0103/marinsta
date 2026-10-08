-- Rode isso se o seu banco já existia antes do CRM.
-- (bancos criados do zero já ganham tudo direto pelo schema.sql)

CREATE TABLE IF NOT EXISTS crm_opportunities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_account_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    segment ENUM('A','B') NOT NULL DEFAULT 'A' COMMENT 'A = indústria/B2B, B = marca/produto premium',
    profile_url VARCHAR(500) NULL,
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
