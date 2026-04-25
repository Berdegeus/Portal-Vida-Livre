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
