# Portal Vida Livre

Base inicial do Portal Vida Livre com frontend estatico em HTML/CSS/JS e backend em PHP puro com respostas JSON.

## Requisitos

- PHP 8.1+
- MySQL ou MariaDB
- Composer

## Como rodar localmente

1. Copie `backend/.env.example` para `backend/.env` se quiser partir de um modelo limpo.
2. Ajuste `backend/.env` com os dados do banco, SMTP e host/porta locais.
3. Instale as dependencias:

```bash
cd backend
composer install
cd ..
```

4. Na raiz do projeto, rode:

```bash
php serve.php
```

5. O `serve.php` cria o banco se necessario, aplica `backend/database/schema.sql` e sobe o servidor PHP.
6. Acesse `http://localhost:8000/frontend/`.

## Criando um usuário administrador

O sistema usa uma tabela separada (`admins`) para administradores. Não há interface de cadastro — o registro deve ser feito diretamente no banco de dados:

```sql
INSERT INTO admins (name, email) VALUES ('Nome do Admin', 'admin@exemplo.com');
```

## Criar o bot do Telegram (para o 2FA administrativo)
1. Abra o Telegram e inicie uma conversa com [@BotFather](https://t.me/botfather)
2. Envie `/newbot` e siga as instruções (escolha nome e username)
3. O BotFather vai retornar um token no formato `123456789:AAE...` — guarde-o
4. Adicione o token e o username do bot em `backend/.env`:

```env
TELEGRAM_BOT_TOKEN=123456789:AAE...
TELEGRAM_BOT_USERNAME=seubot
```

## Rodando o bot do Telegram

O bot precisa rodar como um processo separado do servidor web. Em um terminal adicional, execute:

```bash
php backend/bot/telegram-bot.php
```

Mantenha esse processo ativo enquanto o sistema estiver em uso. Ele fica em loop aguardando mensagens e é responsável por vincular o Telegram dos administradores na primeira autenticação.

## Perguntas de segurança (fallback de login do admin)

Quando o serviço de e-mail ou o Telegram estão indisponíveis, o sistema oferece um fallback: o admin responde 3 perguntas de segurança pré-configuradas para acessar o painel. Máximo de 2 tentativas erradas antes de bloquear por 24 horas.

### Configurando as perguntas

1. Abra `backend/scripts/admin-setup-security-questions.php` e edite o topo do arquivo com seu e-mail e as respostas que quiser usar:

```php
$EMAIL_DO_ADMIN = 'admin@exemplo.com';

$PERGUNTAS = [
    ['pergunta' => 'Qual é o nome da cidade onde você nasceu?',          'resposta' => 'Curitiba'],
    ['pergunta' => 'Qual era o nome do seu primeiro animal de estimação?','resposta' => 'Bolinha'],
    ['pergunta' => 'Qual é o nome da sua escola primária?',               'resposta' => 'Escola XYZ'],
];
```

2. Acesse no navegador (com o servidor rodando):

```
http://localhost:8000/backend/scripts/admin-setup-security-questions.php
```

3. O script confirma o que foi salvo. **Delete o arquivo após usar** — ele não deve ficar acessível.

> As respostas são comparadas sem diferença de maiúsculas/minúsculas. "Curitiba", "curitiba" e "CURITIBA" funcionam igualmente.

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
