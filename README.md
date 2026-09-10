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

## Sobre hospedagem

O painel é Laravel + Filament: precisa de PHP rodando como processo, filesystem
gravável (`storage/`) para cache de views e sessões, e não gosta de cold start.
Isso descarta a Vercel, que não suporta PHP oficialmente.

O que serve, com o Neon como banco em qualquer uma delas: **Railway**, **Render**,
**Laravel Cloud** ou uma VPS pequena. Todas fazem deploy por git a partir deste
repositório.

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
