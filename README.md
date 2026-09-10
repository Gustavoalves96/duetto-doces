# Duetto Doces

ERP interno da confeitaria. Controla estoque de insumos, fichas técnicas,
produção, encomendas, vendas avulsas, despesas e o resultado do mês.

Uso interno, dois usuários. Não tem área do cliente nem pedido online.

## Como rodar

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Abra <http://127.0.0.1:8000/admin>.

O seed cria dois logins e um mês de movimento de exemplo:

| E-mail                          | Senha       |
| ------------------------------- | ----------- |
| `aninha@duettodoces.com.br`  | `senha1234` |
| `socio@duettodoces.com.br`   | `senha1234` |

Para começar do zero, sem os dados de exemplo:

```bash
php artisan migrate:fresh
php artisan make:filament-user
```

## Usuários e senha

Criar usuário: `php artisan make:filament-user`.

Trocar a senha: pela página **Perfil**, no menu do canto superior direito do
painel. Se ninguém conseguir entrar, pelo terminal:

```bash
php artisan usuario:senha            # pergunta qual usuário e a senha nova
```

> **Nunca insira usuário direto no banco** (console SQL do Neon, ferramenta de
> GUI, `DB::table('users')->insert()`). Esses caminhos pulam o cast `hashed` do
> Eloquent e gravam a senha em texto puro. O login então rejeita com
> `This password does not use the Bcrypt algorithm`, e a senha fica legível
> para qualquer um com acesso ao banco. Use sempre os comandos acima.

## Trocar o SQLite por PostgreSQL

O SQLite é só o padrão para subir rápido. Nenhuma migration ou consulta é
específica dele — a troca é só de `.env` seguida de `php artisan migrate --seed`.

Postgres local:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=duetto_doces
DB_USERNAME=postgres
DB_PASSWORD=
```

Neon (serverless, é o destino em produção). Pegue os dados na string de conexão
do painel do Neon — o `sslmode=require` não é opcional, sem ele a conexão cai:

```env
DB_CONNECTION=pgsql
DB_HOST=ep-xxxx-xxxx.sa-east-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=
DB_SSLMODE=require
```

## Deploy: Vercel + Neon

O repositório já vem configurado. Os arquivos que fazem isso funcionar:

| Arquivo | Para quê |
| --- | --- |
| `vercel.json` | runtime PHP 8.4, rotas dos assets e variáveis de ambiente |
| `api/index.php` | entrypoint da lambda; joga o `storage/` gravável para `/tmp` |
| `.vercelignore` | mantém testes e `vendor/` fora do bundle |
| script `vercel` no `composer.json` | `config:cache` e `event:cache` no build |

### 1. Criar o banco no Neon

Crie um projeto no [Neon](https://neon.tech) e copie a connection string. Dela
saem `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`.

O Neon dá **dois endpoints** e a diferença entre eles não é detalhe:

| Endpoint | Quando usar |
| --- | --- |
| `ep-xxxx-pooler.<região>.aws.neon.tech` | a aplicação em produção |
| `ep-xxxx.<região>.aws.neon.tech` (sem `-pooler`) | rodar migrations |

O `-pooler` é um PgBouncer em modo transação. Ele reaproveita conexões do
servidor entre clientes, o que é exatamente o que se quer numa lambda, mas traz
duas consequências:

- **Migrations falham nele.** O DDL do Laravel roda numa transação longa e o
  PgBouncer a interrompe no meio. O sintoma é
  `SQLSTATE[25P02]: current transaction is aborted`. Use o endpoint direto.
- **A aplicação precisa de `DB_EMULATE_PREPARES=true`.** Sem isso o `PREPARE` e
  o `EXECUTE` de um prepared statement podem cair em conexões diferentes e
  abortar a transação — o mesmo erro 25P02, agora em qualquer tela que grave
  estoque. Já está ligado no `vercel.json` e tratado no `config/database.php`.

### 2. Rodar as migrations

As migrations rodam da sua máquina, não no build da Vercel. Aponte o `.env`
local para o Neon usando o endpoint **direto** (sem `-pooler`) e:

```bash
php artisan migrate --force
php artisan make:filament-user
```

Depois volte o `DB_HOST` para o endpoint com `-pooler`, que é o que a aplicação
usa no dia a dia.

### 3. Gerar a chave da aplicação

```bash
php artisan key:generate --show
```

Copie a saída inteira, incluindo o prefixo `base64:`.

### 4. Importar o projeto na Vercel

Em **Add New → Project**, escolha este repositório. Não é preciso mexer em
build command nem output directory — o `vercel.json` cuida disso.

Em **Settings → Environment Variables**, adicione (as demais já estão no
`vercel.json`):

| Variável | Valor |
| --- | --- |
| `APP_KEY` | a saída do passo 3 |
| `APP_URL` | `https://seu-projeto.vercel.app` |
| `DB_HOST` | `ep-xxxx.sa-east-1.aws.neon.tech` |
| `DB_DATABASE` | `neondb` |
| `DB_USERNAME` | `neondb_owner` |
| `DB_PASSWORD` | a senha do Neon |

Faça o deploy e acesse `/admin`. A raiz redireciona para lá.

> Trocar variável de ambiente na Vercel exige **redeploy** para valer: o
> `config:cache` roda no build e congela os valores.

### O que esperar (e o que não esperar)

A Vercel é serverless e não foi feita para PHP. Funciona, mas com limites reais:

- **Cold start.** A primeira requisição depois de um tempo parado leva alguns
  segundos, porque a lambda sobe e o Blade recompila as views em `/tmp`.
  Enquanto o container está quente, a navegação é normal.
- **Nada é gravado em disco.** Sessão e cache moram no Postgres justamente por
  isso — `/tmp` não é compartilhado entre invocações, e sessão em arquivo
  derrubaria o login a cada clique. Se um dia entrar upload de foto de produto,
  vai precisar de um bucket (S3, R2), não do disco local.
- **Sem worker nem agendador.** Hoje não faz falta: nenhuma fila, nenhum job
  agendado. Se precisar, dá para usar Vercel Cron chamando uma rota.
- **Assets do Filament versionados.** Estão no git de propósito (veja o
  comentário no `.gitignore`). Depois de atualizar o Filament rode
  `php artisan filament:assets` e commite o resultado, senão o painel sobe
  sem estilo.

Se o cold start incomodar no uso diário, o mesmo repositório sobe sem alteração
nenhuma em Render, Laravel Cloud ou numa VPS — aí com processo PHP de verdade,
sem esses limites.

## Como o custo funciona

O estoque vive nos **insumos**. O custo do brownie nunca é digitado — ele é
calculado:

```
Compra        → entra insumo, recalcula o custo médio ponderado
Ficha técnica → define quais insumos e quanto rende N unidades
Produção      → baixa os insumos, calcula o custo real do lote
Venda         → congela o custo unitário no item do pedido
```

O congelamento é o que faz o relatório de março continuar igual quando o
chocolate sobe em setembro.

### Ordem de uso

1. **Insumos** — cadastre os ingredientes (sempre na menor unidade: g, ml, un)
2. **Compras** — lance a nota; o custo médio se atualiza sozinho
3. **Receitas** — monte a ficha técnica de cada produto
4. **Produzir** — botão na listagem de receitas; peça o multiplicador da fornada
5. **Pedidos** — encomenda (com fluxo de status) ou venda avulsa (já finalizada)
6. **Despesas** — inclua o pró-labore de vocês dois, senão o lucro é fantasia
7. **Relatórios** — margem por produto, CMV, ponto de equilíbrio

## Decisões que parecem estranhas e não são

- **Estoque negativo é permitido.** Na prática a farinha é usada antes de a
  compra ser lançada. Um sistema que bloqueia isso é abandonado na primeira
  semana. O aviso aparece em vermelho e a produção acontece assim mesmo.
- **Custo médio, nunca o último preço.** Chocolate e manteiga oscilam demais;
  o último preço faz a margem pular sem nada ter mudado na operação.
- **Compra aplicada não deixa editar item.** O custo médio é acumulativo e não
  dá para reescrever o passado. Use **Estornar** na listagem, corrija e salve.
- **Status do pedido não fica no formulário.** É ação na listagem, porque cada
  transição tem efeito colateral no estoque.
- **Movimentações são só leitura.** Toda linha nasceu de uma compra, produção,
  venda, perda ou ajuste.

## Arquitetura

```
app/Support/Decimal.php          aritmética decimal exata (bcmath), sem float
app/Enums/                       status, tipos e unidades
app/Models/                      relacionamentos, casts e scopes
app/Services/
  RegistrarCompra                custo médio ponderado
  ExecutarProducao               insumo -> produto, calcula o custo real
  FinalizarPedido                congela o custo, movimenta o produto
  MovimentarEstoque              perdas e contagem de inventário
  RelatorioFinanceiro            fechamento do período
app/Filament/
  Forms/Components/              CampoDinheiro, CampoQuantidade, CampoTelefone
  Resources/                     telas do painel
  Widgets/                       dashboard
  Pages/Relatorios.php           fechamento
```

Toda regra de negócio mora em `app/Services`. Os resources do Filament chamam
os serviços — nenhum cálculo de estoque ou custo dentro de `Resource`, `Action`
ou `Observer`.

### Dinheiro nunca passa por float

`App\Support\Decimal` embrulha o bcmath. Colunas são `decimal` com precisão
declarada (2 casas para dinheiro, 3 para quantidade, 4 para custo unitário), e
os relatórios somam em PHP com bcmath em vez de `SUM()` para não passar por
ponto flutuante em lugar nenhum.

### Máscaras

Os campos brasileiros estão em `app/Filament/Forms/Components`:

- `CampoDinheiro` — `R$ 1.234,56`, precisão configurável com `->casas(4)`
- `CampoQuantidade` — `1.250,500`, três casas
- `CampoTelefone` — `(11) 98765-4321`, aceita fixo e celular

Elas mascaram na tela e convertem para o formato do banco na desidratação, então
o Eloquent sempre recebe `1234.56`. As aspas do JS da máscara são **simples** de
propósito: o Filament imprime a expressão dentro de `x-mask:dynamic="..."` e
aspas duplas fechariam o atributo no meio, quebrando a máscara sem erro nenhum.

## Testes

```bash
./vendor/bin/pest
```

A cobertura está onde bug dói: custo médio ponderado, produção, congelamento do
custo na venda e fechamento financeiro. Há também um smoke test que abre todas
as telas do painel e confere que a máscara chega correta no HTML.

```bash
./vendor/bin/pint     # formatação
```
