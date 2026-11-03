# ERP Pneus - Neto Rodas

Sistema de gestao para loja de pneus: estoque, PDV (balcao e celular), vendas, CRM, dashboard financeiro e contas a pagar/receber.
PHP 8.1+ e MySQL/MariaDB, sem framework. Pronto para rodar numa VPS com Nginx + PHP-FPM.

## Modulos

| Modulo | O que faz |
|---|---|
| Dashboard | Faturamento, ticket medio, CMV, folha proporcional, despesas pagas e lucro liquido do mes; grafico diario com series por vendedor |
| Estoque | Cadastro completo (GTIN, MPN, medidas, local, custo/preco, imagem), ajuste manual e historico de movimentacoes |
| PDV / PDV Mobile | Venda com busca de cliente e produto, **pagamento dividido** (dinheiro, pix, cartao, prazo, troca), troca entra no estoque como usado |
| Vendas | Historico com filtros (periodo, vendedor, situacao), detalhes e baixa de venda a prazo |
| CRM | Carteira, LTV, ativos/inativos (90 dias), WhatsApp direto, historico de compras |
| Financeiro | Contas a receber (inclui vendas a prazo) e a pagar, com baixa e alerta de atraso |
| Vendedores | Cadastro, pagamento semanal e **link pessoal** do PDV Mobile |

## Rodando localmente

```bash
cp .env.example .env            # ajuste DB_* e defina ADMIN_PASSWORD (min. 10 caracteres)
docker compose up -d            # opcional: MySQL local (usuario/senha/banco: erp)
php bin/migrate.php             # cria as tabelas
php bin/create-admin.php        # cria o usuario admin; depois apague ADMIN_PASSWORD do .env
php -S localhost:8000 -t public
```

Testes (usam o banco `erp_test`, que e recriado a cada execucao):

```bash
composer install && DB_NAME=erp_test composer test
```

## Deploy na VPS

1. Clone o repositorio em `/var/www/erp-netorodas`, copie `.env.example` para `.env` e preencha.
2. Use `deploy/nginx.conf` como base (o document root e **`public/`**) e ative HTTPS com certbot.
3. `php bin/migrate.php && php bin/create-admin.php`.
4. Atualizacoes: `./deploy/deploy.sh`.
5. De permissao de escrita do PHP em `public/uploads/`.

## Seguranca (diferencas em relacao ao sistema antigo)

- Credenciais so no `.env` (fora do Git). **Nunca versione `.env`.**
- Login com senha em hash, sessao com cookie HttpOnly/SameSite e CSRF em todos os formularios.
- PDV desktop exige login. O PDV Mobile abre apenas com o link pessoal do vendedor (revogavel em *Vendedores > Novo link*).
- SQL sempre com prepared statements; saida HTML escapada; upload restrito a JPG/PNG/WebP de ate 3 MB.
- Esquema do banco evolui por migracoes em `migrations/` (nada de `ALTER TABLE` em requisicao).

## Regras de negocio

- Venda e atomica: itens, pagamentos, baixa de estoque e contas a receber ficam na mesma transacao.
- Cada venda tem `request_token` unico: reenviar o formulario nao duplica a venda.
- Estoque insuficiente bloqueia a venda (`ALLOW_NEGATIVE_STOCK=true` no `.env` libera). Produto digitado na hora e criado com estoque zero.
- O custo do produto e copiado para o item no momento da venda, entao o CMV historico nao muda se o custo for editado depois.
- Folha = soma do pagamento semanal dos vendedores ativos / 7 x dias decorridos do mes.
- NF-e marcada exige CPF/CNPJ e endereco completo do cliente.

## Roadmap (fora desta versao)

- Emissao de NF-e (Focus NFe) com trava contra nota duplicada.
- NF-e de entrada (sincronizacao de fornecedores) e CMV por nota.
- Mapa de clientes (geocodificacao e Leaflet; as colunas `geo_*` ja existem).
- Importacao de extrato bancario (CSV/OFX).
- Importacao dos dados do sistema atual (clientes, produtos, vendas).

## Publicar no Railway (sem comprar dominio)

O Railway entrega gratis um endereco `https://nome.up.railway.app`. O projeto ja tem `Dockerfile` e `railway.json`.

1. Suba **todas as pastas** do projeto para um repositorio privado no GitHub (veja `SUBIR-NO-GITHUB.txt`).
2. No Railway: **New Project > Deploy from GitHub repo** e escolha o repositorio.
3. No mesmo projeto: **+ New > Database > MySQL**.
4. No servico do ERP, aba **Variables**, crie so duas variaveis (troque `MySQL` pelo nome exato do servico de banco, se for outro):

   | Variavel | Valor |
   |---|---|
   | `MYSQL_URL` | `${{MySQL.MYSQL_URL}}` |
   | `ADMIN_PASSWORD` | senha com 10+ caracteres |

   (Alternativa: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` separados; tambem funcionam.)
5. **Settings > Volumes**: volume com *mount path* `/var/www/html/public/uploads` (sem ele, as fotos dos produtos somem a cada deploy).
6. **Settings > Networking > Generate Domain**, abra o endereco e entre com `admin` e a senha do passo 4.

Como funciona: o servidor web sobe imediatamente; em segundo plano o sistema espera o banco, aplica as migracoes e cria o admin (so se ainda nao existir usuario). Se o banco nao estiver configurado, o site mostra uma pagina explicando o que falta, em vez de erro generico. Depois do primeiro acesso, apague `ADMIN_PASSWORD`. Atualizar = `git push` na `main`.

### Senha do administrador

- A cada inicializacao, se `ADMIN_PASSWORD` estiver definida, o usuario `ADMIN_USER` e criado ou tem a senha ajustada para esse valor. Basta mudar as variaveis e aguardar o deploy.
- Depois de entrar, **remova `ADMIN_PASSWORD`** das variaveis. Sem ela, nada e sobrescrito e a senha trocada no menu **Minha senha** (minimo 8 caracteres) continua valendo.
