# Marinsta

Painel bem enxuto para conectar sua conta do Instagram e responder comentários
automaticamente por palavra-chave. PHP + MySQL puro (sem framework, sem
Composer) para rodar em qualquer hospedagem compartilhada com cPanel.

## Como funciona

- Você conecta sua conta do Instagram (OAuth oficial da Meta).
- Cria automações: "se o comentário tiver X, Y ou Z, responde com tal texto".
- A Meta chama `webhook.php` toda vez que alguém comenta num post/reels seu.
- O sistema confere as palavras-chave e responde o comentário pela API,
  registrando tudo em **Logs**.

## 1. Criar o app na Meta

1. Acesse https://developers.facebook.com/apps e crie um app do tipo **Business**.
2. Adicione o produto **Instagram** → **API setup with Instagram login**.
3. Em **Instagram Business Login**, cadastre a Redirect URI:
   `https://marinsta.marsdesigner.com.br/connect/callback`
4. Em **Roles → Instagram testers**, adicione a sua própria conta do Instagram
   (precisa ser conta Business ou Creator) e aceite o convite dentro do app do
   Instagram, em Configurações → Apps e sites → Convites de tester.
   > Enquanto o app estiver em modo "Development", ele funciona normalmente
   > para contas cadastradas como tester — **não precisa passar por App Review**
   > para uso pessoal.
5. Anote o **App ID** e o **App Secret** (Configurações do app → Básico).
6. Em **Webhooks**, inscreva o produto Instagram no campo `comments`, usando:
   - Callback URL: `https://marinsta.marsdesigner.com.br/webhook.php`
   - Verify Token: qualquer string que você escolher (mesma do `.env`)

## 2. Banco de dados (cPanel)

1. Em **MySQL Databases**, crie um banco e um usuário com acesso a ele.
2. Abra o **phpMyAdmin**, selecione o banco e importe `migrations/schema.sql`
   (aba "Importar").

## 3. Subir os arquivos

1. Crie o subdomínio `marinsta.marsdesigner.com.br` no cPanel e aponte o
   **Document Root direto para a pasta `public/`** deste projeto (ex:
   `marinsta.marsdesigner.com.br` → `/home/SEUUSUARIO/marinsta/public`).
   Isso é importante: `src/`, `templates/`, `.env` etc. ficam fora da pasta
   pública e não podem ser acessados por navegador.
2. Envie todo o conteúdo da pasta do projeto (via FTP/File Manager) para
   `/home/SEUUSUARIO/marinsta/`.
3. Copie `.env.example` para `.env` e preencha com os dados do banco (passo 2)
   e do app da Meta (passo 1).
4. Gere o hash da sua senha de login do painel:
   ```
   php bin/hash_password.php "sua-senha"
   ```
   Cole o resultado em `ADMIN_PASS_HASH` no `.env`.
5. Acesse `https://marinsta.marsdesigner.com.br`, faça login e clique em
   **Conectar Instagram**.

## 4. Cron de renovação do token

O token de acesso dura ~60 dias. Em **cPanel → Cron Jobs**, crie uma tarefa
diária:

```
php /home/SEUUSUARIO/marinsta/cron/refresh_token.php
```

## Estrutura

```
public/          ponto de entrada web (document root do subdomínio)
  index.php      roteador do painel
  webhook.php    endpoint chamado pela Meta
  assets/
src/             classes PHP (fora do document root)
templates/       telas do painel
migrations/      schema.sql
cron/            script de renovação de token
bin/             utilitário de linha de comando
```

## Limitações conscientes deste MVP

- Só reage a **comentários em posts/reels** (gatilho "Comentário no Feed" do
  Gaio). Resposta automática de DM pode ser adicionada depois seguindo o
  mesmo padrão de `AutomationEngine`.
- Suporta uma conta do Instagram conectada por vez — é o suficiente para uso
  pessoal, mas o schema já tem `ig_account_id` caso queira múltiplas contas
  no futuro.
- Login do painel é usuário/senha único, sem 2FA — está atrás de HTTPS e
  senha, considerado suficiente para uso individual.
