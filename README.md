# Burguer App Delivery

Sistema de delivery em PHP 8.3 e MySQL 8, com catálogo público, cadastro de clientes, checkout, acompanhamento de pedidos e painel administrativo protegido.

## Recursos

- Cadastro e login de clientes com `password_hash` usando Argon2id.
- Sessões PHP protegidas, cookies HttpOnly/Secure/SameSite e rotação no login.
- Proteção CSRF em todas as operações de escrita.
- Rate limiting persistente no login.
- Preços, cupons, taxas e totais recalculados no servidor em centavos.
- Pedidos idempotentes e persistidos no MySQL.
- Consulta de pedido limitada ao proprietário autenticado.
- Painel `/admin` protegido no servidor por papel `admin` ou `manager`.
- Gestão de pedidos, produtos, categorias, adicionais, cupons, áreas, configurações e usuários internos.
- Upload de imagens com validação de MIME real, tamanho e dimensões, armazenado fora do diretório público.
- Auditoria de ações administrativas sem registrar senhas ou sessões.
- Dockerfile e Docker Compose prontos para EasyPanel ou VPS compatível.

## Requisitos

- Docker e Docker Compose, ou PHP 8.3 com extensões `pdo_mysql`, `mbstring` e `fileinfo`.
- MySQL 8.0 ou superior.
- HTTPS em produção.

## Instalação com Docker

1. Copie o arquivo de ambiente:

   ```bash
   cp .env.example .env
   ```

2. Preencha obrigatoriamente no `.env`:

   - `APP_URL`
   - `APP_KEY`, gerada com `php -r "echo bin2hex(random_bytes(32));"`
   - `DB_PASSWORD`
   - `MYSQL_ROOT_PASSWORD`

3. Inicie os containers:

   ```bash
   docker compose up -d --build
   ```

4. Na primeira instalação, o MySQL aplica `database/schema.sql` automaticamente. Em banco existente, execute:

   ```bash
   docker compose exec app php bin/migrate.php
   ```

5. Crie o primeiro administrador pelo terminal. A senha não é gravada no código:

   ```bash
   docker compose exec -it app php bin/create-admin.php "Administrador" admin@seudominio.com
   ```

6. Acesse:

   - Loja: `http://localhost:8080`
   - Painel: `http://localhost:8080/admin`
   - Login administrativo: `http://localhost:8080/admin/login`

## Publicação no EasyPanel

1. Crie um serviço a partir deste repositório e use o `Dockerfile`.
2. Crie um serviço MySQL ou informe um banco MySQL externo.
3. Cadastre as variáveis do `.env.example` na área de ambiente do EasyPanel.
4. Não envie o arquivo `.env` ao repositório.
5. Aponte o domínio para o serviço e habilite HTTPS.
6. Execute `php bin/migrate.php` dentro do container.
7. Execute `php bin/create-admin.php` dentro do container para o primeiro acesso. A senha será solicitada sem ser incluída no comando.
8. Configure backup automático do banco e do volume `storage/uploads` e teste uma restauração.

> Este repositório pode ser público porque contém apenas valores de exemplo. Configure senhas, chaves, domínio e telefone exclusivamente nas variáveis de ambiente da plataforma de implantação.

## Pagamentos online e WhatsApp

Enquanto os provedores não forem definidos, use:

```dotenv
PAYMENT_PROVIDER=disabled
WHATSAPP_PROVIDER=link
```

Nenhum botão de pagamento online é exibido nesse modo. Os pagamentos disponíveis são Pix, dinheiro, débito ou crédito na entrega/retirada. Quando um provedor for escolhido, ele deverá ser integrado no servidor com criação de cobrança pelo valor recalculado, assinatura de webhook, idempotência, conciliação e estorno.

O WhatsApp funciona como link de atendimento pelo número definido em `WHATSAPP_NUMBER`. Uma futura API oficial deverá manter o token exclusivamente no servidor.

## Segurança operacional

- Use senhas exclusivas e MFA no EasyPanel e no provedor Git.
- Mantenha PHP, MySQL e a imagem base atualizados.
- Nunca exponha a porta do MySQL à internet.
- Use HTTPS antes de definir `APP_ENV=production`, pois cookies seguros exigem HTTPS.
- Não registre corpos de checkout, senhas, cookies, tokens ou dados completos de pagamento.
- Revise os logs de autenticação e auditoria.
- Faça backup criptografado e teste restauração regularmente.

## Testes de aceite

Antes de publicar:

```bash
docker compose config
docker compose up -d --build
docker compose exec app php bin/migrate.php
```

Valide manualmente:

1. Cadastro, login e logout do cliente.
2. Tentativa de acesso ao pedido de outro cliente.
3. Acesso direto a `/admin` sem sessão.
4. Login e logout administrativo.
5. Cadastro e ativação de produtos.
6. Cupom, taxa de entrega e cálculo do total.
7. Criação idempotente do pedido.
8. Mudança de status e histórico.
9. Upload inválido, acima de 5 MB ou com MIME falso.
10. Reinício dos containers sem perda dos dados.

## Variáveis de ambiente

Consulte `.env.example`. Variáveis de pagamento e mensageria podem permanecer vazias enquanto seus respectivos provedores estiverem desativados.

