# Portal Vida Livre

Base inicial do Portal Vida Livre com frontend estatico em HTML/CSS/JS e backend em PHP puro com respostas JSON.

## Requisitos

- PHP 8.1+
- MySQL ou MariaDB
- Composer

## Como rodar localmente

1. Instale as dependencias:

```bash
cd backend
composer install
cd ..
```

2. Gere o `backend/.env` local:

```bash
php backend/scripts/setup-local-env.php
```

Se `backend/.env` ja existir, o script reaproveita o que estiver preenchido, nao pergunta esses campos de novo e regrava segredos diretos apenas como `*_OBFUSCATED`. O setup cobre aplicacao, banco, SMTP, Telegram e perguntas de seguranca do admin; ele so pergunta o que estiver faltando ou os blocos opcionais que a pessoa decidir configurar. Se o usuario de manutencao nao tiver senha no ambiente local, pressione Enter quando o script pedir a senha.

3. Aplique schema e seed com o usuario de manutencao configurado no `.env`:

```bash
php backend/scripts/run-schema.php
```

4. Aplique as views, gere as credenciais locais de menor privilegio e valide os grants:

```bash
php backend/scripts/apply-db-security.php
```

5. Na raiz do projeto, rode o servidor:

```bash
php serve.php
```

6. O `serve.php` cria o banco se necessario, aplica `backend/database/schema.sql`/seed com a conexao de manutencao e sobe o servidor PHP.
7. Acesse `http://localhost:8000/frontend/`.

## Gestao local de segredos

Os segredos reais ficam em `backend/.env` ou em variaveis de ambiente da maquina. O `backend/.env.example` deve conter apenas nomes de variaveis, sem senhas, tokens ou chaves reais.

Segredos principais suportam valor direto em variaveis de ambiente e valor ofuscado no arquivo local. Neste projeto, o `backend/.env` deve manter somente as variantes `*_OBFUSCATED`:

```env
APP_KEY_OBFUSCATED=words:v1:1,2,3
DB_PASSWORD_OBFUSCATED=words:v1:4,5,6
```

O formato `words:v1:` usa indices de tokens do arquivo `backend/resources/secret-base.txt`. Os indices sao 1-based e os tokens selecionados sao concatenados em runtime. O token especial `__SPACE__` e reconstruido como espaco literal, permitindo ofuscar segredos que contenham separadores. Essa ofuscacao atende ao criterio academico, mas nao e criptografia forte: qualquer pessoa com acesso ao texto-base e ao mapa consegue reconstruir o valor. Nao use segredos no frontend.

O token especial `__NEWLINE__` e reconstruido como quebra de linha literal, permitindo ofuscar segredos multilinhas como chaves RSA privadas. Para gerar e ofuscar a chave privada RSA usada na criptografia hibrida:

```bash
openssl genrsa 2048 | base64 -w 0; echo
php backend/scripts/obfuscate-secret.php
```

Adicione o resultado no `.env` como `RSA_PRIVATE_KEY_OBFUSCATED`.

No startup local, o backend valida `APP_KEY` e as senhas principais de banco. SMTP e Telegram podem ser configurados depois, quando for testar essas integracoes.

Para gerar um valor ofuscado avulso, como `SMTP_PASSWORD_OBFUSCATED`, `TELEGRAM_BOT_TOKEN_OBFUSCATED` ou respostas das perguntas de seguranca, use:

```bash
php backend/scripts/obfuscate-secret.php
```

Antes de commit, rode uma verificacao local de segredos:

```bash
gitleaks detect --source . --redact --verbose
```

Comando auxiliar simples:

```bash
git grep -n -I -E "APP_KEY=|DB_PASSWORD=|SMTP_PASSWORD=|TELEGRAM_BOT_TOKEN=|client_secret|api[_-]?key|Bearer "
```

## Criando um usuário administrador

O sistema usa uma tabela separada (`admins`) para administradores. Não há interface de cadastro — o registro deve ser feito diretamente no banco de dados:

```sql
INSERT INTO admins (name, email) VALUES ('Nome do Admin', 'admin@exemplo.com');
```

## Criar o bot do Telegram (para o 2FA administrativo)
1. Abra o Telegram e inicie uma conversa com [@BotFather](https://t.me/botfather)
2. Envie `/newbot` e siga as instruções (escolha nome e username)
3. O BotFather vai retornar um token. Guarde esse valor fora do Git.
4. Gere o token ofuscado com `php backend/scripts/obfuscate-secret.php` e adicione o resultado, junto com o username do bot, em `backend/.env`:

```env
TELEGRAM_BOT_TOKEN_OBFUSCATED=
TELEGRAM_BOT_USERNAME=
```

Mantenha apenas `TELEGRAM_BOT_TOKEN_OBFUSCATED` no arquivo local.

## Rodando o bot do Telegram

O bot precisa rodar como um processo separado do servidor web. Em um terminal adicional, execute:

```bash
php backend/bot/telegram-bot.php
```

Mantenha esse processo ativo enquanto o sistema estiver em uso. Ele fica em loop aguardando mensagens e é responsável por vincular o Telegram dos administradores na primeira autenticação.

## Perguntas de segurança (fallback de login do admin)

Quando o serviço de e-mail ou o Telegram estão indisponíveis, o sistema oferece um fallback: o admin responde 3 perguntas de segurança pré-configuradas para acessar o painel. Máximo de 2 tentativas erradas antes de bloquear por 24 horas.

### Configurando as perguntas

1. Configure as perguntas no `backend/.env`. Gere cada resposta com `php backend/scripts/obfuscate-secret.php`:

```env
ADMIN_SECURITY_EMAIL=
ADMIN_SECURITY_QUESTION_1=
ADMIN_SECURITY_ANSWER_1_OBFUSCATED=
ADMIN_SECURITY_QUESTION_2=
ADMIN_SECURITY_ANSWER_2_OBFUSCATED=
ADMIN_SECURITY_QUESTION_3=
ADMIN_SECURITY_ANSWER_3_OBFUSCATED=
```

2. Execute o setup local:

```bash
php backend/scripts/admin-setup-security-questions.php
```

Tambem e possivel acessar no navegador com o servidor rodando:

```
http://localhost:8000/backend/scripts/admin-setup-security-questions.php
```

3. O script confirma apenas as perguntas salvas e nunca imprime as respostas. Depois do setup, remova `ADMIN_SECURITY_ANSWER_*_OBFUSCATED` do `.env` local se nao precisar reconfigurar.

> As respostas sao comparadas sem diferenca de maiusculas/minusculas.

### Testando o fallback

Para simular a falha dos serviços externos sem precisar desligar e-mail ou Telegram de verdade:

1. **Ative a simulação** abrindo esta URL no mesmo navegador que você vai usar para logar:

```
http://localhost:8000/backend/api/admin-simulate-3p-fail.php?enable=1
```

2. **Faça o login normalmente** em `/frontend/admin-login.html` — o e-mail vai "falhar" e você será redirecionado para a tela de perguntas de segurança.

3. **Desative a simulação** após o teste:

```
http://localhost:8000/backend/api/admin-simulate-3p-fail.php?enable=0
```

> A simulação fica gravada na sessão do seu navegador e não afeta outros usuários.

## Logs de auditoria

Todas as ações relevantes são registradas na tabela `audit_logs` do banco de dados e ficam visíveis no painel administrativo (aba "Logs de auditoria").

### Ações de usuário

| Ação | Quando ocorre |
|---|---|
| `user.register` | Cadastro concluído |
| `user.login` | Login com e-mail e senha (sem 2FA) |
| `user.login_failed` | E-mail ou senha incorretos |
| `user.login_2fa_required` | Login iniciado, aguardando código 2FA |
| `user.login_2fa_completed` | Código 2FA validado, login concluído |
| `user.login_2fa_failed` | Código 2FA inválido (cada tentativa) |
| `user.logout` | Logout |
| `user.email_verified` | E-mail confirmado via link |
| `user.password_reset_requested` | Solicitou redefinição de senha |
| `user.password_reset` | Redefiniu a senha via link |
| `user.password_changed` | Alterou a senha estando logado |
| `user.2fa_enabled` | Ativou o 2FA |
| `user.2fa_disabled` | Desativou o 2FA |
| `user.account_deleted` | Excluiu a própria conta |

### Ações administrativas

| Ação | Quando ocorre |
|---|---|
| `admin.login` | Login do admin concluído (após Telegram 2FA) |
| `admin.login_via_security_questions` | Login do admin via perguntas de segurança (fallback) |
| `admin.security_questions_locked` | Conta bloqueada por tentativas incorretas nas perguntas |
| `admin.logout` | Logout do admin |
| `admin.users_viewed` | Admin abriu a lista de usuários |
| `admin.directory_viewed` | Admin abriu a lista do diretório |
| `admin.user_deleted` | Admin excluiu um usuário |
| `admin.directory_deleted` | Admin excluiu uma entrada do diretório |
