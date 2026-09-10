<?php

namespace Database\Seeders;

use App\Enums\CategoriaDespesa;
use App\Enums\FormaPagamento;
use App\Enums\StatusPedido;
use App\Enums\TipoPedido;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Despesa;
use App\Models\Insumo;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\User;
use App\Services\ExecutarProducao;
use App\Services\FinalizarPedido;
use App\Services\MovimentarEstoque;
use App\Services\RegistrarCompra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de exemplo para a confeitaria abrir o painel e já ver como cada
 * número aparece. Tudo passa pelos serviços — nenhum custo é escrito à mão,
 * exatamente como no uso real.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->usuarios();

        $insumos = $this->insumos();
        $produtos = $this->produtos();

        $this->compras($insumos);
        $receitas = $this->receitas($produtos, $insumos);
        $this->producoes($receitas);
        $this->vendas($produtos);
        $this->despesas();
        $this->perdas($insumos, $produtos);
    }

    private function usuarios(): void
    {
        // são dois donos, sem roles nem permissions
        foreach ([
            ['Aninha', 'aninha@duettodoces.com.br'],
            ['Sócio', 'socio@duettodoces.com.br'],
        ] as [$nome, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $nome, 'password' => Hash::make('senha1234')],
            );
        }
    }

    /** @return array<string, Insumo> */
    private function insumos(): array
    {
        $definicoes = [
            // nome, unidade, mínimo
            ['Chocolate meio amargo 50%', 'g', '2000'],
            ['Chocolate ao leite', 'g', '1500'],
            ['Farinha de trigo', 'g', '3000'],
            ['Açúcar refinado', 'g', '3000'],
            ['Açúcar mascavo', 'g', '1000'],
            ['Manteiga sem sal', 'g', '1000'],
            ['Ovos', 'un', '30'],
            ['Cacau em pó', 'g', '500'],
            ['Fermento químico', 'g', '200'],
            ['Sal', 'g', '200'],
            ['Essência de baunilha', 'ml', '100'],
            ['Nozes', 'g', '3000'],   // caro: mantêm folga maior
            ['Gotas de chocolate', 'g', '1000'],
        ];

        $insumos = [];

        foreach ($definicoes as [$nome, $unidade, $minimo]) {
            $insumos[$nome] = Insumo::firstOrCreate(
                ['nome' => $nome],
                ['unidade_base' => $unidade, 'estoque_minimo' => $minimo],
            );
        }

        return $insumos;
    }

    /** @return array<string, Produto> */
    private function produtos(): array
    {
        $definicoes = [
            ['Brownie tradicional', '8.00'],
            ['Brownie com nozes', '9.50'],
            ['Cookie de chocolate', '6.50'],
        ];

        $produtos = [];

        foreach ($definicoes as [$nome, $preco]) {
            $produtos[$nome] = Produto::firstOrCreate(['nome' => $nome], ['preco_venda' => $preco]);
        }

        return $produtos;
    }

    /** @param  array<string, Insumo>  $insumos */
    private function compras(array $insumos): void
    {
        $notas = [
            ['Atacadão', 30, [
                // insumo, quantidade na unidade base (g/ml/un), valor pago
                ['Chocolate meio amargo 50%', '35000', '1218.00'],   // 35 kg a R$ 34,80/kg
                ['Farinha de trigo', '25000', '112.50'],             // R$ 4,50/kg
                ['Açúcar refinado', '25000', '95.00'],               // R$ 3,80/kg
                ['Manteiga sem sal', '20000', '780.00'],             // R$ 39,00/kg
                ['Ovos', '360', '252.00'],                           // R$ 0,70/un
                ['Cacau em pó', '6000', '288.00'],                   // R$ 48,00/kg
            ]],
            ['Mercado do bairro', 18, [
                ['Fermento químico', '1000', '25.00'],
                ['Sal', '2000', '8.40'],
                ['Essência de baunilha', '1000', '56.00'],
                ['Açúcar mascavo', '12000', '118.80'],
                ['Ovos', '240', '175.20'],
                ['Açúcar refinado', '20000', '79.00'],
            ]],
            ['Casa dos Doces', 9, [
                // o chocolate subiu de R$ 34,80 para R$ 39,00 o quilo.
                // é exatamente aqui que a média ponderada mostra serviço:
                // o custo do brownie sobe um pouco, não pula para o preço novo
                ['Chocolate meio amargo 50%', '25000', '975.00'],
                ['Chocolate ao leite', '6000', '198.00'],
                ['Gotas de chocolate', '12000', '456.00'],
                ['Nozes', '8000', '712.00'],
                ['Manteiga sem sal', '20000', '820.00'],
                ['Farinha de trigo', '20000', '94.00'],
            ]],
        ];

        $servico = app(RegistrarCompra::class);

        foreach ($notas as [$fornecedor, $diasAtras, $itens]) {
            $compra = Compra::create([
                'fornecedor' => $fornecedor,
                'data' => now()->subDays($diasAtras)->toDateString(),
            ]);

            foreach ($itens as [$nome, $quantidade, $valor]) {
                $compra->itens()->create([
                    'insumo_id' => $insumos[$nome]->id,
                    'quantidade' => $quantidade,
                    'valor_total' => $valor,
                ]);
            }

            $servico->aplicar($compra);
        }
    }

    /**
     * @param  array<string, Produto>  $produtos
     * @param  array<string, Insumo>  $insumos
     * @return array<string, Receita>
     */
    private function receitas(array $produtos, array $insumos): array
    {
        $definicoes = [
            ['Brownie tradicional', '20', 'Forno a 180 °C por 25 min. Não bater depois da farinha.', [
                ['Chocolate meio amargo 50%', '500'],
                ['Manteiga sem sal', '250'],
                ['Açúcar refinado', '300'],
                ['Farinha de trigo', '180'],
                ['Ovos', '4'],
                ['Cacau em pó', '40'],
                ['Sal', '3'],
                ['Essência de baunilha', '5'],
            ]],
            ['Brownie com nozes', '20', 'Mesma massa do tradicional, nozes por cima antes de assar.', [
                ['Chocolate meio amargo 50%', '500'],
                ['Manteiga sem sal', '250'],
                ['Açúcar refinado', '300'],
                ['Farinha de trigo', '180'],
                ['Ovos', '4'],
                ['Cacau em pó', '40'],
                ['Nozes', '150'],
                ['Sal', '3'],
            ]],
            ['Cookie de chocolate', '24', 'Gelar a massa 30 min antes. Forno a 190 °C por 12 min.', [
                ['Farinha de trigo', '400'],
                ['Manteiga sem sal', '200'],
                ['Açúcar mascavo', '180'],
                ['Açúcar refinado', '100'],
                ['Ovos', '2'],
                ['Gotas de chocolate', '250'],
                ['Fermento químico', '6'],
                ['Sal', '4'],
                ['Essência de baunilha', '5'],
            ]],
        ];

        $receitas = [];

        foreach ($definicoes as [$produto, $rendimento, $preparo, $itens]) {
            $receita = Receita::firstOrCreate(
                ['produto_id' => $produtos[$produto]->id],
                ['nome' => 'Ficha do '.strtolower($produto), 'rendimento' => $rendimento, 'observacoes' => $preparo],
            );

            foreach ($itens as [$nome, $quantidade]) {
                $receita->itens()->firstOrCreate(
                    ['insumo_id' => $insumos[$nome]->id],
                    ['quantidade' => $quantidade],
                );
            }

            $receitas[$produto] = $receita->load('itens');
        }

        return $receitas;
    }

    /** @param  array<string, Receita>  $receitas */
    private function producoes(array $receitas): void
    {
        $servico = app(ExecutarProducao::class);

        // uma leva de fornadas por semana, com multiplicadores variados —
        // é o que mostra a média ponderada do custo do produto em ação
        $agenda = [
            ['Brownie tradicional', '18'],   // 18 fornadas x 20 un = 360
            ['Cookie de chocolate', '15'],   // 15 x 24 = 360
            ['Brownie com nozes', '14'],     // 14 x 20 = 280
            ['Brownie tradicional', '16'],
            ['Cookie de chocolate', '12'],
            ['Brownie com nozes', '12'],
            ['Brownie tradicional', '20'],
            ['Cookie de chocolate', '14'],
            ['Brownie com nozes', '12'],
        ];

        // distribuídas nos últimos 28 dias, da mais antiga para a mais nova
        foreach ($agenda as $indice => [$produto, $multiplicador]) {
            $servico->executar(
                $receitas[$produto],
                $multiplicador,
                now()->subDays(27 - ($indice * 3))->toDateString(),
            );
        }
    }

    /** @param  array<string, Produto>  $produtos */
    private function vendas(array $produtos): void
    {
        $clientes = collect([
            ['Maria Silva', '11987654321'],
            ['João Pereira', '11976543210'],
            ['Escritório Andrade', '1133224455'],
            ['Carla Souza', '11991234567'],
        ])->map(fn (array $c) => Cliente::firstOrCreate(
            ['nome' => $c[0]],
            ['telefone' => $c[1]],
        ));

        $servico = app(FinalizarPedido::class);

        // movimento de balcão dos últimos 28 dias: 2 a 3 vendas por dia,
        // com um pico no fim de semana, como numa confeitaria de verdade
        $nomes = array_keys($produtos);
        $semente = 20260910;

        for ($diasAtras = 27; $diasAtras >= 0; $diasAtras--) {
            $dia = now()->subDays($diasAtras);
            $vendasNoDia = $dia->isWeekend() ? 11 : 7;

            for ($n = 0; $n < $vendasNoDia; $n++) {
                // pseudoaleatório determinístico: o seed é sempre o mesmo
                $semente = ($semente * 1103515245 + 12345) % 2147483648;
                $escolha = $nomes[$semente % count($nomes)];
                $quantidade = (string) (4 + ($semente % 10));

                $forma = match ($semente % 4) {
                    0 => FormaPagamento::Dinheiro,
                    1 => FormaPagamento::Pix,
                    2 => FormaPagamento::Debito,
                    default => FormaPagamento::Credito,
                };

                $bruto = bcmul($quantidade, (string) $produtos[$escolha]->preco_venda, 2);

                $pedido = Pedido::create([
                    'tipo' => TipoPedido::Avulsa,
                    'status' => StatusPedido::Entregue,
                    'data' => $dia->toDateString(),
                    'forma_pagamento' => $forma,
                    // a taxa da maquininha sai do faturamento líquido
                    'taxa_operadora' => bcdiv(bcmul($bruto, $forma->taxaSugerida(), 4), '100', 2),
                ]);

                $this->itens($pedido, $produtos, [[$escolha, $quantidade]]);
                $servico->efetivar($pedido->refresh());
            }
        }

        // encomendas em vários pontos do fluxo
        $encomendas = [
            [0, [['Brownie tradicional', '30']], StatusPedido::Confirmado, 3, '50.00'],
            [1, [['Brownie com nozes', '20'], ['Cookie de chocolate', '20']], StatusPedido::EmProducao, 2, '80.00'],
            [2, [['Cookie de chocolate', '40']], StatusPedido::Pronto, 1, '100.00'],
            [3, [['Brownie tradicional', '25']], StatusPedido::Orcamento, 7, '0'],
        ];

        foreach ($encomendas as $indice => [$diasAtras, $itens, $status, $prazo, $sinal]) {
            $pedido = Pedido::create([
                'tipo' => TipoPedido::Encomenda,
                'status' => StatusPedido::Orcamento,
                'cliente_id' => $clientes[$indice % $clientes->count()]->id,
                'data' => now()->subDays($diasAtras)->toDateString(),
                'data_entrega' => now()->addDays($prazo)->toDateString(),
                'sinal_pago' => $sinal,
                'forma_pagamento' => FormaPagamento::Pix,
            ]);

            $this->itens($pedido, $produtos, $itens);

            if ($status !== StatusPedido::Orcamento) {
                $servico->mudarStatus($pedido->refresh(), $status);
            }
        }
    }

    /** @param  array<string, Produto>  $produtos */
    private function itens(Pedido $pedido, array $produtos, array $itens): void
    {
        foreach ($itens as [$nome, $quantidade]) {
            $pedido->itens()->create([
                'produto_id' => $produtos[$nome]->id,
                'quantidade' => $quantidade,
                'preco_unitario' => $produtos[$nome]->preco_venda,
            ]);
        }
    }

    private function despesas(): void
    {
        $fixas = [
            [CategoriaDespesa::ProLabore, 'Pró-labore Aninha', '1500.00', true],
            [CategoriaDespesa::ProLabore, 'Pró-labore sócio', '1500.00', true],
            [CategoriaDespesa::Aluguel, 'Aluguel da cozinha', '900.00', true],
            [CategoriaDespesa::Energia, 'Conta de luz', '280.00', true],
            [CategoriaDespesa::Gas, 'Botijão de gás', '120.00', true],
            [CategoriaDespesa::Internet, 'Internet', '99.90', true],
        ];

        foreach ($fixas as [$categoria, $descricao, $valor, $recorrente]) {
            Despesa::firstOrCreate([
                'categoria' => $categoria,
                'descricao' => $descricao,
                'data' => now()->startOfMonth()->toDateString(),
            ], ['valor' => $valor, 'recorrente' => $recorrente]);
        }

        $variaveis = [
            [CategoriaDespesa::Embalagem, 'Caixas e fitas', '186.40', 11],
            [CategoriaDespesa::Marketing, 'Impulsionamento no Instagram', '60.00', 8],
            [CategoriaDespesa::Transporte, 'Entregas do mês', '145.00', 3],
        ];

        foreach ($variaveis as [$categoria, $descricao, $valor, $diasAtras]) {
            Despesa::firstOrCreate([
                'categoria' => $categoria,
                'descricao' => $descricao,
            ], ['valor' => $valor, 'data' => now()->subDays($diasAtras)->toDateString()]);
        }
    }

    /**
     * @param  array<string, Insumo>  $insumos
     * @param  array<string, Produto>  $produtos
     */
    private function perdas(array $insumos, array $produtos): void
    {
        $servico = app(MovimentarEstoque::class);

        $servico->perdaDeInsumo($insumos['Manteiga sem sal'], '250', 'Manteiga passou do prazo');
        $servico->perdaDeProduto($produtos['Brownie tradicional'], '4', 'Fornada queimou na borda');
    }
}
