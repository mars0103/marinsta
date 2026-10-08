-- Rode isso se o seu banco já existia antes dessas funções.
-- (bancos criados do zero já ganham tudo direto pelo schema.sql)

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

ALTER TABLE automations
    ADD COLUMN target_scope ENUM('all','next','specific') NOT NULL DEFAULT 'all' AFTER match_type,
    ADD COLUMN target_media_id VARCHAR(64) NULL AFTER target_scope,
    ADD COLUMN reply_pack_id INT UNSIGNED NULL AFTER reply_text,
    ADD COLUMN dm_pack_id INT UNSIGNED NULL AFTER dm_text,
    ADD COLUMN flow_json LONGTEXT NULL AFTER dm_pack_id,
    ADD CONSTRAINT fk_automations_reply_pack FOREIGN KEY (reply_pack_id) REFERENCES reply_packs(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_automations_dm_pack FOREIGN KEY (dm_pack_id) REFERENCES reply_packs(id) ON DELETE SET NULL;
