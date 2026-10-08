-- Rode isso se o seu banco já existia antes do botão na DM.

ALTER TABLE automations
    ADD COLUMN dm_button_label VARCHAR(20) NULL AFTER dm_pack_id,
    ADD COLUMN dm_button_url VARCHAR(500) NULL AFTER dm_button_label;
