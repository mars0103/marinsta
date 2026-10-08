-- Rode isso se o seu banco já existia antes do script de mensagem no CRM.

ALTER TABLE crm_opportunities
    ADD COLUMN outreach_script TEXT NULL AFTER consent_context;
