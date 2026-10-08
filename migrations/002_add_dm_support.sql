-- Rode isso se o seu banco já foi criado antes do suporte a resposta por DM.
-- (bancos criados do zero já ganham essas colunas direto pelo schema.sql)

ALTER TABLE automations
    ADD COLUMN send_dm TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_text,
    ADD COLUMN dm_text TEXT NULL AFTER send_dm;

ALTER TABLE automation_logs
    ADD COLUMN dm_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_sent;
