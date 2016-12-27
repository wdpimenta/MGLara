<?php

namespace MGLara\Jobs;

use MGLara\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use MGLara\Models\EstoqueMes;
use MGLara\Jobs\EstoqueGeraMovimentoNegocioProdutoBarra;
use MGLara\Jobs\EstoqueGeraMovimentoNotaFiscalProdutoBarra;

/**
 * @property int $codproduto
 * @property bool $fiscal
 * @property bool $fisico
 */
class EstoqueGeraMovimentoProduto extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels, DispatchesJobs;
    
    protected $codproduto;
    protected $fiscal;
    protected $fisico;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $codproduto, bool $fisico = true, bool $fiscal = true)
    {
        $this->codproduto = $codproduto;
        $this->fisico = $fisico;
        $this->fiscal = $fiscal;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('EstoqueGeraMovimentoProduto', ['codproduto' => $this->codproduto]);
        
        if ($this->fisico) {
            
            $corte = Carbon::createFromFormat('Y-m-d H:i:s', EstoqueMes::CORTE_FISICO);

            $sql = "select npb.codnegocioprodutobarra
                from tblprodutobarra pb
                inner join tblnegocioprodutobarra npb on (npb.codprodutobarra = pb.codprodutobarra)
                inner join tblnegocio n on (n.codnegocio = npb.codnegocio)
                where pb.codproduto = {$this->codproduto}
                and n.lancamento >= '" . $corte->format('Y-m-d H:i:s') . "'
                order by npb.codnegocioprodutobarra
                ";

            $rows = DB::select($sql);

            foreach ($rows as $row) {
                $this->dispatch((new EstoqueGeraMovimentoNegocioProdutoBarra($row->codnegocioprodutobarra))->onQueue('medium'));
            }
        }
        
        if ($this->fiscal) {
            
            $corte = Carbon::createFromFormat('Y-m-d H:i:s', EstoqueMes::CORTE_FISCAL);

            $sql = "select npb.codnotafiscalprodutobarra
                from tblprodutobarra pb
                inner join tblnotafiscalprodutobarra npb on (npb.codprodutobarra = pb.codprodutobarra)
                inner join tblnotafiscal n on (n.codnotafiscal = npb.codnotafiscal)
                where pb.codproduto = {$this->codproduto}
                and n.saida >= '" . $corte->format('Y-m-d H:i:s') . "'
                order by npb.codnotafiscalprodutobarra
                ";

            $rows = DB::select($sql);

            foreach ($rows as $row) {
                $this->dispatch((new EstoqueGeraMovimentoNotaFiscalProdutoBarra($row->codnotafiscalprodutobarra))->onQueue('medium'));
            }
        }
        
    }
}
