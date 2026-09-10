# CLAUDE.md

Contexto do projeto para o Claude Code. Leia antes de escrever qualquer código.

## O que é

ERP interno para uma confeitaria pequena (brownies e cookies), operada por duas
pessoas. Controla estoque de insumos, fichas técnicas, produção, encomendas,
vendas avulsas, despesas e relatórios financeiros.

**Uso interno apenas.** Não existe área do cliente, não existe pedido online,
não existe catálogo público. Só os dois donos acessam o painel.

## Stack

- PHP 8.2+ / Laravel 12 ou 13
- Filament v5 (painel admin)
- PostgreSQL (MySQL também serve)
- Sem front-end separado. Tudo dentro do painel Filament.

Atenção com a versão: a partir do Filament v4 as actions foram unificadas no
namespace `Filament\Actions\*`. Tutoriais e exemplos da v3 usam
`Filament\Tables\Actions\*` e vão quebrar. Sempre confira a documentação da v5.

## Princípio central do domínio

O estoque vive nos **insumos**, não no produto acabado. O custo de um brownie
nunca é digitado à mão — ele é **calculado** a partir da ficha técnica:

```
Compra        → entra insumo no estoque, recalcula custo médio ponderado
Ficha técnica → define quais insumos e quanto de cada um rende N unidades
Produção      → baixa os insumos, calcula o custo real, gera produto acabado
Venda         → congela o custo unitário no item do pedido
```

Se em algum momento o código permitir cadastrar um custo de produto
manualmente, algo está errado no design. Todos os relatórios financeiros
dependem desse cálculo estar correto.

## Schema

```
insumos          id, nome, unidade_base (g|ml|un), estoque_atual decimal(12,3),
                 custo_medio decimal(12,4), estoque_minimo decimal(12,3)

compras          id, fornecedor, data, total decimal(12,2)
compra_itens     compra_id, insumo_id, quantidade decimal(12,3),
                 valor_total decimal(12,2)

receitas         id, produto_id, rendimento decimal(12,3), observacoes
receita_itens    receita_id, insumo_id, quantidade decimal(12,3)

produtos         id, nome, preco_venda decimal(12,2),
                 estoque_atual decimal(12,3), custo_unitario decimal(12,4)

producoes        id, receita_id, data, quantidade_produzida decimal(12,3),
                 custo_total decimal(12,2)

movimentacoes    id, insumo_id (nullable), produto_id (nullable),
                 tipo (entrada|saida|perda|ajuste), quantidade decimal(12,3),
                 custo_unitario decimal(12,4), observacao

clientes         id, nome, telefone

pedidos          id, cliente_id (nullable), tipo (encomenda|avulsa),
                 status, data_entrega (nullable), desconto decimal(12,2),
                 sinal_pago decimal(12,2), forma_pagamento,
                 taxa_operadora decimal(12,2)

pedido_itens     pedido_id, produto_id, quantidade decimal(12,3),
                 preco_unitario decimal(12,2), custo_unitario decimal(12,4)

despesas         id, categoria, descricao, valor decimal(12,2), data,
                 pedido_id (nullable), recorrente boolean
```

Status do pedido: `orcamento` → `confirmado` → `em_producao` → `pronto` →
`entregue`, mais `cancelado` a partir de qualquer ponto.

### Regras não negociáveis do schema

- **Nunca use `float` ou `double`.** Dinheiro e quantidade são sempre `decimal`.
  Erro de arredondamento binário em CMV vira diferença de centavos que ninguém
  consegue rastrear depois.
- **Guarde tudo na menor unidade** (g, ml, unidade). A compra é em kg, a receita
  usa 180 g — converta na entrada, nunca no meio do cálculo.
- **`pedido_itens.custo_unitario` é congelado** no momento da venda, copiado de
  `produtos.custo_unitario`. Nunca calcule margem histórica com o custo atual,
  senão o relatório do mês passado muda toda vez que o preço do chocolate sobe.

## Regras de negócio

### Custo médio ponderado (na compra)

Ao registrar uma compra, para cada item:

```php
$valorEstoque = $insumo->estoque_atual * $insumo->custo_medio;
$novaQtd      = $insumo->estoque_atual + $item->quantidade;
$insumo->custo_medio = $novaQtd > 0
    ? ($valorEstoque + $item->valor_total) / $novaQtd
    : 0;
$insumo->estoque_atual = $novaQtd;
```

Nunca use o último preço pago. Chocolate e manteiga oscilam muito e o último
preço distorce a margem.

### Produção

Ao produzir a partir de uma receita, com um `$multiplicador` (0.5 = meia
fornada, 3 = três fornadas):

1. Para cada item da receita: baixa `quantidade * multiplicador` do insumo e
   acumula `quantidade * custo_medio` no custo total.
2. Gera `rendimento * multiplicador` unidades do produto.
3. Recalcula o `custo_unitario` do produto por média ponderada, misturando o
   estoque anterior com o lote novo.
4. Registra a `producao` e as `movimentacoes`.

O multiplicador é obrigatório. Sem ele, o usuário acaba criando "Receita brownie
grande" e "Receita brownie pequena", que é o começo do caos.

### Estoque insuficiente

**Permita produzir mesmo sem estoque suficiente**, com aviso. Estoque negativo
no insumo é aceitável. Na vida real eles usam a farinha antes de lançar a
compra, e um sistema que bloqueia isso é abandonado na primeira semana. Mostre
um badge vermelho nos insumos negativos ou abaixo do mínimo.

### Concorrência

Todo cálculo que lê e escreve estoque roda dentro de `DB::transaction()` com
`lockForUpdate()` no model sendo alterado. São só dois usuários, mas isso evita
corrupção em jobs e importações.

### Perdas

O tipo de movimentação `perda` existe desde o início e precisa de interface.
Fornada queimada, insumo vencido. Sem isso o estoque nunca bate com a realidade
e o sistema deixa de ser usado.

### Taxas e despesas

- Taxa de maquininha/delivery é gravada em `pedidos.taxa_operadora` e abatida do
  faturamento líquido. Ignorar isso infla o lucro.
- Pró-labore dos dois donos entra como `despesa` recorrente. Sem isso o "lucro"
  é só o pagamento disfarçado do trabalho deles.

## Arquitetura do código

- Regra de negócio em classes de serviço em `app/Services/`, não em model,
  não em resource do Filament. Ex.: `RegistrarCompra`, `ExecutarProducao`,
  `FinalizarPedido`.
- Resources do Filament chamam os serviços. Nenhum cálculo de estoque ou custo
  dentro de `Resource`, `Action` ou `Observer`.
- Models expõem relacionamentos, casts e scopes. Sem lógica pesada.
- Enums PHP nativos para `status`, `tipo` e `unidade_base`, com
  `HasLabel`/`HasColor` do Filament para a UI.
- Testes com Pest para os serviços de custo e produção. É onde bug dói.

## Convenções

- Nomes de tabelas, colunas, models e rotas em **português**, como no schema
  acima. Comentários e mensagens de UI também em português.
- Migrations sempre com `decimal` explícito e precisão declarada.
- `php artisan make:filament-resource` para gerar, depois ajustar à mão.

## Padrões no Filament

- **Compras e Pedidos**: `Repeater` para os itens, salvando tudo de uma vez.
  Dispare o serviço no `after()` do create/edit.
- **Produção**: não é CRUD. É uma `Action` no `ReceitaResource` chamada
  "Produzir", que abre modal pedindo o multiplicador e chama `ExecutarProducao`.
- **Mudança de status do pedido**: `Action` na listagem ("Marcar como
  entregue"), não um select dentro do formulário.
- **Dashboard**: `StatsOverviewWidget` com faturamento do mês, lucro líquido,
  encomendas pendentes e contagem de insumos abaixo do mínimo. `ChartWidget`
  com faturamento diário.
- Sem roles, sem permissions, sem multi-tenancy. Dois usuários no painel padrão.

## Relatórios

- Faturamento bruto e líquido (descontando taxas) por período
- CMV a partir de `pedido_itens.custo_unitario`
- Margem de contribuição **por produto** — mostra qual sabor sustenta o negócio
- Lucro líquido = faturamento líquido − CMV − despesas do período
- Ponto de equilíbrio: quantas unidades por mês pagam a operação

## Ordem de construção

Não construa tudo antes de usar. Siga esta ordem:

1. Insumos (CRUD + estoque)
2. Compras (com custo médio ponderado)
3. Receitas / ficha técnica
4. Ação de produzir
5. Pedidos (encomenda e avulsa)
6. Despesas
7. Dashboard e relatórios

Depois do passo 4 o sistema já entrega o custo real do produto, que é seu valor
central. O uso real a partir daí vai mudar quais relatórios fazem sentido — por
isso eles ficam por último.
