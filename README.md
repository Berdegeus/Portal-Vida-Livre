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
