-- Rode isso se o seu banco já existia antes de separar site/Instagram no CRM.

ALTER TABLE crm_opportunities
    ADD COLUMN instagram_url VARCHAR(500) NULL AFTER profile_url;
