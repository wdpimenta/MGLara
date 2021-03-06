<?php

namespace MGLara\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;

use Illuminate\Support\Facades\DB;

use MGLara\Jobs\EstoqueCalculaCustoMedio;
use MGLara\Models\EstoqueMes;
use MGLara\Models\EstoqueMovimento;
use MGLara\Models\EstoqueMovimentoTipo;
use MGLara\Models\EstoqueLocal;
use MGLara\Models\NotaFiscal;
use MGLara\Models\NotaFiscalProdutoBarra;
use MGLara\Models\ProdutoBarra;
use MGLara\Models\Produto;
use Carbon\Carbon;

class EstoqueAjustaFiscalCommand extends Command
{
    use DispatchesJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'estoque:ajusta-fiscal {metodo?} {--codestoquelocal=} {--auto} {--data=} {--ordem-inversa} {--produto=} {--codproduto=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ajusta Estoque Fiscal';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        switch ($this->argument('metodo')) {

            case 'transfere-mesmo-produto-local':
                $this->transfereMesmoProdutoLocal();
                break;

            case 'transfere-mesmo-ncm':
                $this->transfereMesmoNcm();
                break;

            case 'gera-notas-fiscais-transferencia':
                $this->geraNotasFiscaisTransferencia();
                break;

            case 'transfere-manual':
                $this->transfereManual();
                break;

            case 'zera-saldo':
                $this->zeraSaldo();
                break;

            default:
                $this->metodoDesconhecido();
                break;
        }
    }

    public function metodoDesconhecido()
    {
        $this->line('');
        $this->info('Utilização:');
        $this->line('');
        $this->line('php artisan estoque:ajusta-fiscal metodo --codestoquelocal=? --auto=true');
        $this->line('');
        $this->info('Métodos Disponíveis:');
        $this->line('');
        $this->line('- transfere-mesmo-produto-local: Ajusta estoque negativo da variação transferindo o saldo de outra variacao do mesmo produto, do mesmo local de estoque!');
        $this->line('- transfere-mesmo-ncm: Ajusta estoque negativo transferindo o saldo de outro produto com o mesmo NCM!');
        $this->line('- gera-notas-fiscais-transferencia: Gera notas de transferencia de uma filial para outra a fim de corrigir o estoque negativo!');
        $this->line('- transfere-manual: Solicita codestoquemes de onde transferir saldo para cobrir saldo negativo!');
	$this->line('- zera-saldo: Zera saldo de estoque');
    }

    public function zeraSaldo()
    {
        $codproduto = $this->option('codproduto');
        $prod = Produto::findOrFail($codproduto);

        foreach ($prod->ProdutoVariacaoS as $pv) {
            foreach ($pv->EstoqueLocalProdutoVariacaoS as $elpv) {
                foreach ($elpv->EstoqueSaldoS()->where('fiscal', true)->get() as $es) {
                    if ($es->saldoquantidade == 0) {
                        continue;
                    }
                    DB::beginTransaction();
                    $data = carbon::parse(($es->saldoquantidade>0)?'2018-12-31':'2018-01-01');
                    $mes = EstoqueMes::buscaOuCria($pv->codprodutovariacao, $elpv->codestoquelocal, true, $data);
                    $mov = new EstoqueMovimento();
                    $mov->codestoquemes = $mes->codestoquemes;
                    $mov->codestoquemovimentotipo = 1002; // Ajuste
                    $mov->data = $data;
                    $mov->manual = true;
                    if ($es->saldoquantidade > 0) {
                        $mov->saidaquantidade = $es->saldoquantidade;
                        $mov->saidavalor = $es->saldovalor;
                    } else {
                        $mov->entradaquantidade = abs($es->saldoquantidade);
                        $mov->entradavalor = abs($es->saldovalor);
                    }
                    $mov->observacoes = 'via comando zera-saldo';
                    if (!$mov->save()) {
                      throw new Exception('Erro ao Salvar Movimento de Destino!');
                    }
                    if (empty($prod->inativo)) {
                        $prod->inativo = $data;
                        $prod->save();
                    }
                    DB::commit();
                    $this->dispatch((new EstoqueCalculaCustoMedio($mes->codestoquemes))->onQueue('urgent'));
                    $this->info("Criado Ajuste em {$mes->codestoquemes}({$mov->codestoquemovimento})!");
                }
            }
        }

    }

    public function transfereSaldo($quantidade, Carbon $data, $codprodutovariacaoorigem, $codestoquelocalorigem, $codprodutovariacaodestino, $codestoquelocaldestino)
    {
        $quantidade = floor($quantidade);

        DB::beginTransaction();

        $mes_origem = EstoqueMes::buscaOuCria($codprodutovariacaoorigem, $codestoquelocalorigem, true, $data);
        $mes_destino = EstoqueMes::buscaOuCria($codprodutovariacaodestino, $codestoquelocaldestino, true, $data);

        $tipo = EstoqueMovimentoTipo::findOrFail(4201);

        $mov_origem = new EstoqueMovimento();
        $mov_origem->codestoquemes = $mes_origem->codestoquemes;
        $mov_origem->codestoquemovimentotipo = $tipo->codestoquemovimentotipoorigem;
        $mov_origem->data = $data;
        $mov_origem->manual = true;
        $mov_origem->saidaquantidade = $quantidade;
        if (!$mov_origem->save()) {
            throw new Exception('Erro ao Salvar Movimento de Destino!');
        }

        $mov_destino = new EstoqueMovimento();
        $mov_destino->codestoquemes = $mes_destino->codestoquemes;
        $mov_destino->codestoquemovimentotipo = $tipo->codestoquemovimentotipo;
        $mov_destino->codestoquemovimentoorigem = $mov_origem->codestoquemovimento;
        $mov_destino->data = $data;
        $mov_destino->manual = true;
        $mov_destino->entradaquantidade = $quantidade;

        if (!$mov_destino->save()) {
            throw new Exception('Erro ao Salvar Movimento de Destino!');
        }

        $this->info("Trasferido {$quantidade} de {$mes_origem->codestoquemes}({$mov_origem->codestoquemovimento}) para {$mes_destino->codestoquemes}({$mov_destino->codestoquemovimento})!");
        $this->line('');

        DB::commit();

        $this->dispatch((new EstoqueCalculaCustoMedio($mes_origem->codestoquemes))->onQueue('urgent'));
        $this->dispatch((new EstoqueCalculaCustoMedio($mes_destino->codestoquemes))->onQueue('urgent'));

        // aguarda dois segundos para rodar recalculo dos custos medios
        sleep(2);
    }

    public function geraNotasFiscaisTransferencia()
    {
        // Pega opcao do estoquelocal
        $codestoquelocal = $this->option('codestoquelocal');

        if (empty($codestoquelocal)) {
            $this->line('');
            $this->error('codestoquelocal não informado! Utilize --codestoquelocal=?');
            $this->line('');
            return;
        }

        // Instancia Estoque Local
        $el = EstoqueLocal::findOrFail($codestoquelocal);

        // Busca saldos negativos do estoquelocal
        $sql = "
            select p.codproduto, p.produto, elpv.codestoquelocal, elpv.codestoquelocalprodutovariacao, el.estoquelocal, es.customedio, pv.codprodutovariacao, pv.variacao, es.saldoquantidade, es.saldovalor, es.codestoquesaldo, (select mes.codestoquemes from tblestoquemes mes where mes.codestoquesaldo = es.codestoquesaldo order by mes desc limit 1) as codestoquemes
            from tblproduto p
            inner join tblprodutovariacao pv on (pv.codproduto = p.codproduto)
            inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao)
            inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
            inner join tblfilial f on (f.codfilial = el.codfilial)
            inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
            where elpv.codestoquelocal = $codestoquelocal
            and coalesce(es.saldoquantidade, 0) <= -1
            order by p.codproduto, saldoquantidade
        ";

        $dados = DB::select($sql);

        // Pergunta se deseja continuar
        if (!$this->confirm(sizeof($dados) . ' registros com saldo negativo encontrados! Continuar [y|N]')) {
            return;
        }

        // Percorre negativos
        foreach ($dados as $negativo) {

            // Mostra registro
            $this->line("{$negativo->codproduto} - {$negativo->produto} - {$negativo->variacao} - {$negativo->saldoquantidade}");

            // Busca alternativas da mesma variacao
            $sql = "
                select coalesce(pv.variacao, '{ Sem Variacao }') as variacao, pv.codprodutovariacao, elpv.codestoquelocal, es.codestoquesaldo, es.saldoquantidade, es.customedio, es.codestoquelocalprodutovariacao
                from tblprodutovariacao pv
                inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao)
                inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
                where pv.codprodutovariacao = {$negativo->codprodutovariacao}
                and es.codestoquelocalprodutovariacao != {$negativo->codestoquelocalprodutovariacao}
                and es.saldoquantidade >= 1
                order by elpv.codestoquelocal, es.saldoquantidade DESC, pv.variacao ASC NULLS FIRST
            ";

            $alternativas = DB::select($sql);

            // Soma quantidade disponivel das alternativas
            $qtd_alternativas = 0;
            foreach ($alternativas as $alternativa) {
                if (isset($cache_saldos[$alternativa->codestoquesaldo])) {
                    $qtd_alternativas += $cache_saldos[$alternativa->codestoquesaldo];
                } else {
                    $qtd_alternativas += $alternativa->saldoquantidade;
                }
            }

            // se a quantidade disponivel for menor que o saldo negativo
            // busca somente pelo produto, independente da variacao
            $saldo = abs($negativo->saldoquantidade);
            if ($qtd_alternativas < $saldo) {

                $sql = "
                    select coalesce(pv.variacao, '{ Sem Variacao }') as variacao, pv.codprodutovariacao, elpv.codestoquelocal, es.codestoquesaldo, es.saldoquantidade, es.customedio, es.codestoquelocalprodutovariacao
                    from tblprodutovariacao pv
                    inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao)
                    inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
                    where pv.codproduto = {$negativo->codproduto}
                    and es.codestoquelocalprodutovariacao != {$negativo->codestoquelocalprodutovariacao}
                    and es.saldoquantidade >= 1
                    order by elpv.codestoquelocal, es.saldoquantidade DESC, pv.variacao ASC NULLS FIRST
                ";

                $alternativas = DB::select($sql);

            }

            // Percorre alternativas
            $i=0;
            while ($saldo >= 1 && ($i <= (sizeof($alternativas) -1))) {

                // faz um cache do saldo, controlando quanto ja foi utilizado
                if (!isset($cache_saldos[$alternativas[$i]->codestoquesaldo])) {
                    $cache_saldos[$alternativas[$i]->codestoquesaldo] = $alternativas[$i]->saldoquantidade;
                }

                // utiliza a menor quantidade entre o saldo disponivel e o negativo
                $quantidade = min([$saldo, $cache_saldos[$alternativas[$i]->codestoquesaldo]]);

                // se tinha algo disponivel
                if ($quantidade >= 1) {

                    // se a nota ja tiver mais de 500 itens, não deixa utilizar mais ela
                    if (isset($nfs[$alternativas[$i]->codestoquelocal])) {
                        if ($nfs[$alternativas[$i]->codestoquelocal]->NotaFiscalProdutoBarraS()->count() >= 500) {
                            unset($nfs[$alternativas[$i]->codestoquelocal]);
                        }
                    }

                    // cria nota fiscal
                    if (!isset($nfs[$alternativas[$i]->codestoquelocal])) {
                        $nfs[$alternativas[$i]->codestoquelocal] = new NotaFiscal;
                        $nfs[$alternativas[$i]->codestoquelocal]->codestoquelocal = $alternativas[$i]->codestoquelocal;
                        $nfs[$alternativas[$i]->codestoquelocal]->codfilial = $nfs[$alternativas[$i]->codestoquelocal]->EstoqueLocal->codfilial;
                        $nfs[$alternativas[$i]->codestoquelocal]->codpessoa = $el->Filial->codpessoa;
                        $nfs[$alternativas[$i]->codestoquelocal]->modelo = NotaFiscal::MODELO_NFE;
                        $nfs[$alternativas[$i]->codestoquelocal]->codnaturezaoperacao = 15; //Transferencia de Saida
                        $nfs[$alternativas[$i]->codestoquelocal]->codoperacao = $nfs[$alternativas[$i]->codestoquelocal]->NaturezaOperacao->codoperacao;
                        $nfs[$alternativas[$i]->codestoquelocal]->serie = 1;
                        $nfs[$alternativas[$i]->codestoquelocal]->numero = 0;
                        $nfs[$alternativas[$i]->codestoquelocal]->emitida = true;
                        $nfs[$alternativas[$i]->codestoquelocal]->emissao = new Carbon('now');
                        $nfs[$alternativas[$i]->codestoquelocal]->saida = $nfs[$alternativas[$i]->codestoquelocal]->emissao;
                        $nfs[$alternativas[$i]->codestoquelocal]->save();
                        $geradas[$nfs[$alternativas[$i]->codestoquelocal]->codnotafiscal] = 0;
                    }

                    // pega o produto barra
                    if (!$pb = ProdutoBarra::where('codprodutovariacao', '=', $alternativas[$i]->codprodutovariacao)->whereNull('codprodutoembalagem')->first()) {
                        if (!$pb = ProdutoBarra::where('codprodutovariacao', '=', $alternativas[$i]->codprodutovariacao)->first()) {
                            die('Sem Produto Barra');
                            continue;
                        }
                    }

                    // cria o item da nota
                    $nfpb = new NotaFiscalProdutoBarra;
                    $nfpb->codprodutobarra = $pb->codprodutobarra;
                    $nfpb->codnotafiscal = $nfs[$alternativas[$i]->codestoquelocal]->codnotafiscal;
                    $nfpb->quantidade = $quantidade;
                    $nfpb->valorunitario = $alternativas[$i]->customedio;
                    if ($nfpb->valorunitario == 0) {
                        $nfpb->valorunitario = $negativo->customedio;
                    }
                    if ($nfpb->valorunitario == 0) {
                        $nfpb->valorunitario = $pb->Produto->preco * 0.7;
                    }
                    if (!empty($pb->codprodutoembalagem)) {
                        $nfpb->quantidade /= $pb->ProdutoEmbalagem->quantidade;
                        $nfpb->valorunitario *= $pb->ProdutoEmbalagem->quantidade;
                    }
                    $nfpb->valortotal = $nfpb->quantidade * $nfpb->valorunitario;
                    $nfpb->calculaTributacao();
                    $nfpb->save();

                    // incrementa a quantidade de itens da nota gerada
                    $geradas[$nfs[$alternativas[$i]->codestoquelocal]->codnotafiscal]++;

                    // diminui a quantidade do saldo e do cache
                    $saldo -= $quantidade;
                    $cache_saldos[$alternativas[$i]->codestoquesaldo] -= $quantidade;
                }

                // proxima alternativa
                $i++;
            }
        }

        // lista notas geradas e a quantidade de notas
        foreach ($geradas as $codnotafiscal => $itens) {
            $this->line("Gerada NF $codnotafiscal com $itens itens");
        }
    }

    public function transfereMesmoProdutoLocal()
    {
        $codestoquelocal = $this->option('codestoquelocal');
        $auto = $this->option('auto');
        $data = $this->option('data');

        if (empty($data)) {
            $date = Carbon::now();
        } else {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $data);
        }

        if (empty($codestoquelocal)) {
            $this->line('');
            $this->error('codestoquelocal não informado! Utilize --codestoquelocal=?');
            $this->line('');
            return;
        }

        $sql = "
            select p.codproduto, p.produto, elpv.codestoquelocal, elpv.codestoquelocalprodutovariacao, el.estoquelocal, pv.codprodutovariacao, pv.variacao, es.saldoquantidade, es.saldovalor, es.codestoquesaldo, (select mes.codestoquemes from tblestoquemes mes where mes.codestoquesaldo = es.codestoquesaldo order by mes desc limit 1) as codestoquemes
            from tblproduto p
            inner join tblprodutovariacao pv on (pv.codproduto = p.codproduto)
            inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao)
            inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
            inner join tblfilial f on (f.codfilial = el.codfilial)
            inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
            where elpv.codestoquelocal = $codestoquelocal
            and coalesce(es.saldoquantidade, 0) < 0
            order by p.codproduto, saldoquantidade
        ";

        $dados = DB::select($sql);

        if (!$this->confirm(sizeof($dados) . ' registros com saldo negativo encontrados! Continuar [y|N]')) {
            return;
        }

        foreach ($dados as $negativo) {
            $this->line("{$negativo->codproduto} - {$negativo->produto} - {$negativo->variacao} - {$negativo->saldoquantidade}");

            $sql = "
                select coalesce(pv.variacao, '{ Sem Variacao }') as variacao, pv.codprodutovariacao, elpv.codestoquelocal, es.codestoquesaldo, es.saldoquantidade, es.codestoquelocalprodutovariacao
                from tblprodutovariacao pv
                inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao and elpv.codestoquelocal = {$negativo->codestoquelocal})
                inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
                where pv.codproduto = {$negativo->codproduto}
                and es.codestoquelocalprodutovariacao != {$negativo->codestoquelocalprodutovariacao}
                and es.saldoquantidade > 0
                order by es.saldoquantidade DESC, pv.variacao ASC NULLS FIRST
            ";

            $alternativas = DB::select($sql);
            if (sizeof($alternativas) == 0) {
                $this->info('Sem alternativas');
                continue;
            }

            $headers = ['#', 'Variação', 'Saldo'];

            $data=[];
            $choices=[];
            foreach ($alternativas as $i => $alt) {
                $choices[$i] = $i;
                $data[] = [
                    'indice' => $i,
                    'variacao' => $alt->variacao,
                    'saldo' => $alt->saldoquantidade,
                ];
            }
            $choices[] = 'Nenhum';

            $this->table($headers, $data);

            if (!$auto) {
                $escolhido = $this->choice('Transferir de qual alternativa?', $choices, false);

                if ($escolhido == 'Nenhum') {
                    continue;
                }
            } else {
                $escolhido = 0;
            }

            $origem = $alternativas[$escolhido];

            $quantidade = min([abs($negativo->saldoquantidade), abs($origem->saldoquantidade)]);
            //$data = Carbon::now();
            $codprodutovariacaoorigem = $origem->codprodutovariacao;
            $codestoquelocalorigem = $origem->codestoquelocal;
            $codprodutovariacaodestino = $negativo->codprodutovariacao;
            $codestoquelocaldestino = $negativo->codestoquelocal;

            $this->transfereSaldo(
                $quantidade,
                $date,
                $codprodutovariacaoorigem,
                $codestoquelocalorigem,
                $codprodutovariacaodestino,
                $codestoquelocaldestino);

        }

    }


    public function transfereMesmoNcm()
    {
        $auto = $this->option('auto');

        $ncm_sem_alternativa = [0];
        
        $produtos_sem_saldo = [0];
        $codestoquemes_ultimo = 0;
        
        while ($dados = DB::select("
            select p.codproduto, p.produto, pv.variacao, coalesce(p.preco, 0) as preco, el.sigla, em.saldoquantidade, em.saldovalor, em.customedio, em.codestoquemes, em.mes, elpv.codprodutovariacao, elpv.codestoquelocal, n.ncm, p.codncm, f.codempresa, es.saldoquantidade as saldoquantidade_atual
            from tblestoquesaldo es
            inner join tblestoquemes em on (em.codestoquemes = (select em2.codestoquemes from tblestoquemes em2 where em2.codestoquesaldo = es.codestoquesaldo and em2.mes <= '2018-12-31' order by mes desc limit 1))
            inner join tblestoquelocalprodutovariacao elpv on (elpv.codestoquelocalprodutovariacao = es.codestoquelocalprodutovariacao)
            inner join tblprodutovariacao pv on (pv.codprodutovariacao = elpv.codprodutovariacao)
            inner join tblproduto p on (p.codproduto = pv.codproduto)
            inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
            inner join tblfilial f on (f.codfilial = el.codfilial)
            inner join tblncm n on (n.codncm = p.codncm)
            where es.fiscal = true
            and em.saldoquantidade < -1
            and f.codempresa = 1
            and p.codncm not in (" . implode(', ', $ncm_sem_alternativa) . ")
            and em.codestoquemes != $codestoquemes_ultimo
            --order by em.mes, n.ncm, p.preco DESC, p.produto, elpv.codestoquelocal, pv.variacao nulls first
            order by n.ncm ASC, p.preco DESC, p.produto ASC, el.codestoquelocal, pv.variacao
            --limit 1
        ")) {
            $negativo = $dados[0];
            $codestoquemes_ultimo = $negativo->codestoquemes;
            $this->line('');
            $this->line('');
            $this->line('');
            $this->line('');
            $this->info("https://sistema.mgpapelaria.com.br/MGLara/estoque-mes/$negativo->codestoquemes");


            $this->table(
                [
                    'Mês',
                    '#',
                    'Produto',
                    'Variação',
                    'Venda',
                    'Loc',
                    'Qtd',
                    'Atual',
                    'Val',
                    'Médio',
                    'NCM',
                ], [[
                    $negativo->mes,
                    $negativo->codproduto,
                    $negativo->produto,
                    $negativo->variacao,
                    $negativo->preco,
                    $negativo->sigla,
                    $negativo->saldoquantidade,
                    $negativo->saldoquantidade_atual,
                    $negativo->saldovalor,
                    $negativo->customedio,
                    $negativo->ncm,
                ]]);

            $sql = "
                select
                    p.codproduto
                    , p.produto
                    , p.preco
                    , coalesce(fiscal.saldoquantidade_atual, 0) - coalesce(fisico.saldoquantidade_atual, 0) as sobra_atual
                    , coalesce(fiscal.saldoquantidade, 0) - coalesce(fisico.saldoquantidade, 0) as sobra
                    , fisico.saldoquantidade as fisico_saldoquantidade
                    , fiscal.saldoquantidade as fiscal_saldoquantidade
                    , fiscal.customedio as fiscal_customedio
                from tblproduto p
                left join (
                    select pv.codproduto, sum(em.saldoquantidade) as saldoquantidade, sum(em.saldovalor) as saldovalor, avg(em.customedio) as customedio, sum(es.saldoquantidade) as saldoquantidade_atual
                    from tblestoquelocalprodutovariacao elpv
                    inner join tblprodutovariacao pv on (pv.codprodutovariacao = elpv.codprodutovariacao)
                    inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
                    inner join tblfilial f on (f.codfilial = el.codfilial)
                    inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = false)
                    inner join tblestoquemes em on (em.codestoquemes = (select em2.codestoquemes from tblestoquemes em2 where em2.codestoquesaldo = es.codestoquesaldo and em2.mes <= '{$negativo->mes}' order by mes desc limit 1))
                    where f.codempresa = {$negativo->codempresa}
                    group by pv.codproduto
                ) fisico on (fisico.codproduto = p.codproduto)
                left join (
                    select pv.codproduto, sum(em.saldoquantidade) as saldoquantidade, sum(em.saldovalor) as saldovalor, avg(em.customedio) as customedio, sum(es.saldoquantidade) as saldoquantidade_atual
                    from tblestoquelocalprodutovariacao elpv
                    inner join tblprodutovariacao pv on (pv.codprodutovariacao = elpv.codprodutovariacao)
                    inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
                    inner join tblfilial f on (f.codfilial = el.codfilial)
                    inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
                    inner join tblestoquemes em on (em.codestoquemes = (select em2.codestoquemes from tblestoquemes em2 where em2.codestoquesaldo = es.codestoquesaldo and em2.mes <= '{$negativo->mes}' order by mes desc limit 1))
                    where f.codempresa = {$negativo->codempresa}
                    group by pv.codproduto
                ) fiscal on (fiscal.codproduto = p.codproduto)
                where p.codtipoproduto = 0
                AND p.codncm = {$negativo->codncm}
                AND coalesce(fiscal.saldoquantidade_atual, 0) > coalesce(fisico.saldoquantidade_atual, 0)
                AND coalesce(fiscal.saldoquantidade, 0) > coalesce(fisico.saldoquantidade, 0)
                and coalesce(fiscal.saldoquantidade_atual, 0) > 0
                and coalesce(fiscal.saldoquantidade, 0) > 0
                and p.codproduto not in (" . implode(', ', $produtos_sem_saldo) . ")
                order by abs(p.preco - {$negativo->preco})
                limit 10
            ";

            $alt_prods = DB::select($sql);

            if (sizeof($alt_prods) == 0) {
                $ncm_sem_alternativa[] = $negativo->codncm;
                continue;
            }

            $data=[];
            $choices=[];
            foreach ($alt_prods as $i => $alt) {
                $choices[$i] = $alt->codproduto;
                $data[$alt->codproduto] = [
                    $alt->codproduto,
                    $alt->produto,
                    $alt->preco,
                    $alt->sobra_atual,
                    $alt->sobra,
                    $alt->fisico_saldoquantidade,
                    $alt->fiscal_saldoquantidade,
                    $alt->fiscal_customedio,
                ];
                $cods[$alt->codproduto] = $i;
            }

            $this->table([
                '#',
                'Produto',
                'Preço',
                'Sobra At',
                'Sobra',
                'Fisico',
                'Fiscal',
                'Médio',
            ], $data);

            if (!$auto) {
                $codproduto = $this->choice('Transferir de qual alternativa?', $choices, false);
            } else {
                $codproduto = $alt_prods[0]->codproduto;
                $this->error($codproduto);
            }

            $produto = $alt_prods[$cods[$codproduto]];

            $sql = "
                select
                    em_fiscal.codestoquemes
                    , el.codestoquelocal
                    , el.sigla
                    , pv.codprodutovariacao
                    , pv.variacao
                    , es_fiscal.saldoquantidade - case when (es_fisico.saldoquantidade > 0) then es_fisico.saldoquantidade else 0 end as sobra_atual
                    , em_fiscal.saldoquantidade - case when (em_fisico.saldoquantidade > 0) then em_fisico.saldoquantidade else 0 end as sobra
                    , es_fisico.saldoquantidade as fisico_saldoquantidade
                    , es_fiscal.saldoquantidade as fiscal_saldoquantidade
                from tblprodutovariacao pv
                inner join tblestoquelocalprodutovariacao elpv on (elpv.codprodutovariacao = pv.codprodutovariacao)
                inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
                left join tblestoquesaldo es_fiscal on (es_fiscal.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es_fiscal.fiscal = true)
                left join tblestoquemes em_fiscal on (em_fiscal.codestoquemes = (select em.codestoquemes from tblestoquemes em where em.mes <= '{$negativo->mes}' and em.codestoquesaldo = es_fiscal.codestoquesaldo order by mes desc limit 1))
                left join tblestoquesaldo es_fisico on (es_fisico.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es_fisico.fiscal = false)
                left join tblestoquemes em_fisico on (em_fisico.codestoquemes = (select em.codestoquemes from tblestoquemes em where em.mes <= '{$negativo->mes}' and em.codestoquesaldo = es_fisico.codestoquesaldo order by mes desc limit 1))
                where pv.codproduto = {$produto->codproduto}
                and (coalesce(es_fiscal.saldoquantidade, 0) > 0 or coalesce(es_fisico.saldoquantidade, 0) > 0)
                order by es_fiscal.saldoquantidade - case when (es_fisico.saldoquantidade > 0) then es_fisico.saldoquantidade else 0 end desc
            ";

            $alt_meses = DB::select($sql);

            $data=[];
            $choices=[];
            foreach ($alt_meses as $i => $alt) {
                $choices[$i] = $alt->codestoquemes;
                $data[$alt->codestoquemes] = [
                    $alt->codestoquemes,
                    $alt->sigla,
                    $alt->variacao,
                    $alt->sobra_atual,
                    $alt->sobra,
                    $alt->fisico_saldoquantidade,
                    $alt->fiscal_saldoquantidade,
                ];
                $cods[$alt->codestoquemes] = $i;
            }

            $this->table([
                '#',
                'Loc',
                'Variacao',
                'Sobra At',
                'Sobra',
                'Fisico',
                'Fiscal',
            ], $data);

            if (!$auto) {
                $codestoquemes = $this->choice('Transferir de qual alternativa?', $choices, false);
            } else {
                $codestoquemes = null;
                foreach ($alt_meses as $alt) {
                    if ($alt->sobra_atual > 0 and $alt->sobra > 0) {
                        $codestoquemes = $alt->codestoquemes;
                        break;
                    }
                }
            }
            
            if ($codestoquemes == null) {
                $produtos_sem_saldo[] = $produto->codproduto;
                continue;
            }
            
            $mes = $alt_meses[$cods[$codestoquemes]];
            
            $quatidade = ($negativo->saldoquantidade_atual < $negativo->saldoquantidade)?abs($negativo->saldoquantidade_atual):abs($negativo->saldoquantidade);

            $quantidade = min([
                $produto->sobra_atual,
                $produto->sobra,
                $mes->sobra_atual,
                $mes->sobra,
                $quatidade,
            ]);

            if ($quantidade <= 0) {
                $this->error('Estoquemes escolhido não tem saldo!');
                continue;
            }

            if (!$auto) {
                $quantidade = $this->ask('Informe a quantidade:', $quantidade);
            }

            if ($quantidade <= 0) {
                continue;
            }

            $this->transfereSaldo(
                $quantidade,
                Carbon::createFromFormat('Y-m-d', $negativo->mes)->endOfMonth(),
                $mes->codprodutovariacao,
                $mes->codestoquelocal,
                $negativo->codprodutovariacao,
                $negativo->codestoquelocal
            );
            
        }


    }

    public function transfereManual()
    {

	/*
        $sql = "
            select p.codproduto, p.produto, pv.variacao, p.preco, el.sigla, em.saldoquantidade, em.saldovalor, em.customedio, em.codestoquemes, em.mes, elpv.codprodutovariacao, elpv.codestoquelocal, n.ncm
            from tblestoquemes em
            inner join tblestoquesaldo es on (es.codestoquesaldo = em.codestoquesaldo and es.fiscal = true)
            inner join tblestoquelocalprodutovariacao elpv on (elpv.codestoquelocalprodutovariacao = es.codestoquelocalprodutovariacao)
            inner join tblprodutovariacao pv on (pv.codprodutovariacao = elpv.codprodutovariacao)
            inner join tblproduto p on (p.codproduto = pv.codproduto)
            inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
            inner join tblncm n on (n.codncm = p.codncm)
            where em.saldoquantidade < 0
            order by em.mes, n.ncm, p.preco DESC, p.produto, elpv.codestoquelocal, pv.variacao nulls first
            limit 1
            ";
	*/
	/*
	$sql = "
		with sld_2017 as (
			select 
				es.codestoquesaldo, 
				(
					select em_u.codestoquemes 
					from tblestoquemes em_u 
					where em_u.codestoquesaldo = es.codestoquesaldo 
					and em_u.mes <= '2017-12-31'::date 
					order by em_u.mes desc
					limit 1
				) as codestoquemes
			from tblestoquesaldo es 
			where es.fiscal = true
		)
		select 
			p.codproduto, p.produto, pv.variacao, p.preco, el.sigla, em.saldoquantidade, em.saldovalor, em.customedio, em.codestoquemes, em.mes, elpv.codprodutovariacao, elpv.codestoquelocal, n.ncm
		from sld_2017
		inner join tblestoquemes em on (em.codestoquemes = sld_2017.codestoquemes)
		inner join tblestoquesaldo es on (es.codestoquesaldo = em.codestoquesaldo and es.fiscal = true)
		inner join tblestoquelocalprodutovariacao elpv on (elpv.codestoquelocalprodutovariacao = es.codestoquelocalprodutovariacao)
		inner join tblprodutovariacao pv on (pv.codprodutovariacao = elpv.codprodutovariacao)
		inner join tblproduto p on (p.codproduto = pv.codproduto)
		inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
		inner join tblfilial f on (f.codfilial = el.codfilial)
		inner join tblncm n on (n.codncm = p.codncm)
		where em.saldoquantidade < 0
		and f.codempresa = 1
		order by n.ncm, p.preco DESC, p.produto, p.codproduto, elpv.codestoquelocal, pv.variacao nulls first, em.mes
		limit 1
		";
	*/
	$sql = "
		with fisico as (
			select elpv.codestoquelocal, elpv.codprodutovariacao, em.saldoquantidade, em.saldovalor, em.customedio, es.saldoquantidade as saldoquantidade_atual, es.saldovalor as saldovalor_atual
			from tblestoquelocalprodutovariacao elpv
			inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
			inner join tblfilial f on (f.codfilial = el.codfilial)
			inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = false)
			inner join tblestoquemes em on (em.codestoquemes = (select em2.codestoquemes from tblestoquemes em2 where em2.codestoquesaldo = es.codestoquesaldo and em2.mes <= '2018-12-31' order by mes desc limit 1))
			where f.codempresa = 1
		), fiscal as (
			select 
				elpv.codestoquelocal, 
				elpv.codprodutovariacao, 
				em.saldoquantidade, 
				em.saldovalor, 
				em.customedio, 
				es.saldoquantidade as saldoquantidade_atual, 
				es.saldovalor as saldovalor_atual,
				em.codestoquemes,
				em.mes
			from tblestoquelocalprodutovariacao elpv
			inner join tblestoquelocal el on (el.codestoquelocal = elpv.codestoquelocal)
			inner join tblfilial f on (f.codfilial = el.codfilial)
			inner join tblestoquesaldo es on (es.codestoquelocalprodutovariacao = elpv.codestoquelocalprodutovariacao and es.fiscal = true)
			inner join tblestoquemes em on (em.codestoquemes = (select em2.codestoquemes from tblestoquemes em2 where em2.codestoquesaldo = es.codestoquesaldo and em2.mes <= '2018-12-31' order by mes desc limit 1))
			where f.codempresa = 1
		)
		select 
			n.ncm
			, p.codproduto
			, p.produto
			, p.preco
			, p.inativo
			, pv.codprodutovariacao
			, pv.variacao
			, coalesce(fiscal.codestoquelocal, fisico.codestoquelocal) as codestoquelocal
			, fiscal.saldoquantidade as fiscal
			, fisico.saldoquantidade as fisico
			, fiscal.saldoquantidade_atual as fiscal_atual
			, fisico.saldoquantidade_atual as fisico_atual
			, fiscal.codestoquemes
			, fiscal.mes
		from tblproduto p
		inner join tblprodutovariacao pv on (pv.codproduto = p.codproduto)
		inner join tblncm n on (n.codncm = p.codncm)
		left join fiscal on (fiscal.codprodutovariacao = pv.codprodutovariacao)
		full join fisico on (fisico.codprodutovariacao = pv.codprodutovariacao and fisico.codestoquelocal = fiscal.codestoquelocal)
		where fiscal.saldoquantidade < 0
		--or (fisico.saldoquantidade_atual > 0 and fiscal.saldoquantidade_atual < fisico.saldoquantidade_atual)
		--and fisico.saldoquantidade_atual != fisico.saldoquantidade
	";

	$produto = $this->option('produto');
	if (!empty($produto)) {
		$sql .= "
			and p.produto ilike '$produto'
		";
	}

        $ordem_inversa = $this->option('ordem-inversa');
	if ($ordem_inversa) {
		$sql .= "
                        order by n.ncm DESC, p.preco DESC, p.produto DESC, fiscal.codestoquelocal, pv.variacao

		";
	} else {
                $sql .= "
                        order by n.ncm ASC, p.preco ASC, p.produto ASC, fiscal.codestoquelocal, pv.variacao
                ";
	}
	$sql .= "
		--offset 20
		limit 100
	";

	$dados = DB::select($sql);
	$i = 0;
        $data = Carbon::createFromFormat('Y-m-d', '2018-12-31')->endOfMonth();

        while ($i < sizeof($dados))
        {
            $negativo = $dados[$i];
            $i++;
            $this->line('');
            $this->line('');
            $this->line('');
            $this->line('');
            $this->info("http://sistema.mgpapelaria.com.br/MGLara/estoque-mes/$negativo->codestoquemes");

            $meta = ($negativo->fisico > 0 && empty($negativo->inativo))?$negativo->fisico:0;
            $falta = $meta - $negativo->fiscal;

            $meta_atual = ($negativo->fisico_atual > 0 && empty($negativo->inativo))?$negativo->fisico_atual:0;
            $falta_atual = $meta_atual - $negativo->fiscal_atual;
            $falta = ($falta_atual > $falta)?$falta_atual:$falta;

            $this->table(
                [
                    '#',
                    'Produto',
                    'Variação',
                    'Venda',
                    'Loc',
                    'Falta',
                    'Fiscal',
                    'Fisico',
                    'Fiscal A',
                    'Fisico A',
                    'NCM',
                ], [[
                    $negativo->codproduto,
                    $negativo->produto,
                    $negativo->variacao,
                    $negativo->preco,
                    $negativo->codestoquelocal,
                    $falta,
                    $negativo->fiscal,
                    $negativo->fisico,
                    $negativo->fiscal_atual,
                    $negativo->fisico_atual,
                    $negativo->ncm,
                ]]);

            do {

                $codestoquemes_origem = $this->ask('Informe o codestoquemes para transferir o saldo:');

                if (!$mes_origem = EstoqueMes::find($codestoquemes_origem)) {
                    $this->error('Estoque Mes não localizado!');
                    continue;
                }

                $this->table(
                    [
                        '#',
                        'Produto',
                        'Variação',
                        'Venda',
                        'Loc',
                        'Qtd',
                        'Val',
                        'Médio',
                        'NCM',
                    ], [[
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->ProdutoVariacao->codproduto,
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->ProdutoVariacao->Produto->produto,
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->ProdutoVariacao->variacao,
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->ProdutoVariacao->Produto->preco,
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->EstoqueLocal->sigla,
                        $mes_origem->EstoqueSaldo->saldoquantidade,
                        $mes_origem->EstoqueSaldo->saldovalor,
                        $mes_origem->EstoqueSaldo->customedio,
                        $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->ProdutoVariacao->Produto->Ncm->ncm,
                    ]]);

                if ($mes_origem->EstoqueSaldo->saldoquantidade <= 0) {
                    $this->error('Este produto não tem saldo de estoque disponível!');
                    continue;
                }

                break;
                /*
                if ($this->confirm('Transferir deste Saldo?', true) == true) {
                    break;
                }
                */

            } while (true);

            $quantidade = min([abs($falta), abs($mes_origem->saldoquantidade)]);
            $quantidade = $this->ask('Informe a quantidade:', $quantidade);

            if ($quantidade <= 0) {
                continue;
            }

            $this->transfereSaldo(
                $quantidade,
                $data,
                $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->codprodutovariacao,
                $mes_origem->EstoqueSaldo->EstoqueLocalProdutoVariacao->codestoquelocal,
                $negativo->codprodutovariacao,
                $negativo->codestoquelocal
                );


        }

    }


}
