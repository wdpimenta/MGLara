<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace MGLara\Models;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

/**
 * Campos
 * @property  bigint                         $codestoquesaldo                    NOT NULL DEFAULT nextval('tblestoquesaldo_codestoquesaldo_seq'::regclass)
 * @property  bigint                         $codestoquelocalprodutovariacao             NOT NULL
 * @property  boolean                        $fiscal                             NOT NULL
 * @property  numeric(14,3)                  $saldoquantidade                    
 * @property  numeric(14,2)                  $saldovalor                         
 * @property  numeric(14,6)                  $customedio                 
 * @property  timestamp                      $dataentrada
 * @property  timestamp                      $ultimaconferencia
 * @property  timestamp                      $alteracao                          
 * @property  bigint                         $codusuarioalteracao                
 * @property  timestamp                      $criacao                            
 * @property  bigint                         $codusuariocriacao                  
 * @property  bigint                         $codestoquelocal                    NOT NULL
 *
 * Chaves Estrangeiras
 * @property  Produto                        $Produto                       
 * @property  Usuario                        $UsuarioAlteracao
 * @property  Usuario                        $UsuarioCriacao
 * @property  EstoqueLocalProdutoVariacao    $EstoqueLocalProdutoVariacao
 *
 * Tabelas Filhas
 * @property  EstoqueMes[]                   $EstoqueMesS
 * @property  EstoqueSaldoConferencia[]      $EstoqueSaldoConferenciaS
 */

class EstoqueSaldo extends MGModel
{
    protected $table = 'tblestoquesaldo';
    protected $primaryKey = 'codestoquesaldo';
    protected $fillable = [
        'fiscal',
        'saldoquantidade',
        'saldovalor',
        'customedio',
        'codestoquelocalprodutovariacao',
        'ultimaconferencia',
    ];
    protected $dates = [
        'alteracao',
        'criacao',
        'ultimaconferencia',
        'dataentrada',
    ];
    
    // Chaves Estrangeiras
    public function UsuarioAlteracao()
    {
        return $this->belongsTo(Usuario::class, 'codusuarioalteracao', 'codusuario');
    }

    public function UsuarioCriacao()
    {
        return $this->belongsTo(Usuario::class, 'codusuariocriacao', 'codusuario');
    }

    public function EstoqueLocalProdutoVariacao()
    {
        return $this->belongsTo(EstoqueLocalProdutoVariacao::class, 'codestoquelocalprodutovariacao', 'codestoquelocalprodutovariacao');
    }


    // Tabelas Filhas
    public function EstoqueMesS()
    {
        return $this->hasMany(EstoqueMes::class, 'codestoquesaldo', 'codestoquesaldo');
    }
    
    public function EstoqueSaldoConferenciaS()
    {
        return $this->hasMany(EstoqueSaldoConferencia::class, 'codestoquesaldo', 'codestoquesaldo');
    }
    
    public static function buscaOuCria($codprodutovariacao, $codestoquelocal, $fiscal)
    {
        $elpv = EstoqueLocalProdutoVariacao::buscaOuCria($codprodutovariacao, $codestoquelocal);

        $es = self::where('codestoquelocalprodutovariacao', $elpv->codestoquelocalprodutovariacao)->where('fiscal', $fiscal)->first();
        if ($es == false)
        {
            $es = new EstoqueSaldo;
            $es->codestoquelocalprodutovariacao = $elpv->codestoquelocalprodutovariacao;
            $es->fiscal = $fiscal;
            $es->save();
        }
        return $es;
    }
    
    public static function totais($agrupamento, $valor = 'custo', $filtro = [])
    {
        //$query = DB::table('tblestoquesaldo');
        $query = DB::table('tblestoquelocalprodutovariacao');

        if ($agrupamento != 'variacao') {
            $query->groupBy('fiscal');
            $query->groupBy('tblestoquelocal.codestoquelocal');
            $query->groupBy('tblestoquelocal.estoquelocal');
        }
        
        $query->join('tblestoquelocal', 'tblestoquelocal.codestoquelocal', '=', 'tblestoquelocalprodutovariacao.codestoquelocal');
        $query->join('tblprodutovariacao', 'tblprodutovariacao.codprodutovariacao', '=', 'tblestoquelocalprodutovariacao.codprodutovariacao');
        $query->join('tblproduto', 'tblproduto.codproduto', '=', 'tblprodutovariacao.codproduto');
        $query->leftJoin('tblestoquesaldo', 'tblestoquesaldo.codestoquelocalprodutovariacao', '=', 'tblestoquelocalprodutovariacao.codestoquelocalprodutovariacao');
        $query->leftJoin('tblsubgrupoproduto', 'tblsubgrupoproduto.codsubgrupoproduto', '=', 'tblproduto.codsubgrupoproduto');
        $query->leftJoin('tblgrupoproduto', 'tblgrupoproduto.codgrupoproduto', '=', 'tblsubgrupoproduto.codgrupoproduto');
        $query->leftJoin('tblfamiliaproduto', 'tblfamiliaproduto.codfamiliaproduto', '=', 'tblgrupoproduto.codfamiliaproduto');
        
        
        switch ($agrupamento) {
            case 'variacao':
                $query->select(
                    DB::raw(
                        ' saldoquantidade
                        , ' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ' as saldovalor
                        , estoqueminimo
                        , estoquemaximo
                        , fiscal
                        , tblprodutovariacao.codprodutovariacao as coditem
                        , tblproduto.produto || \' » \' || coalesce(tblprodutovariacao.variacao, \'{ Sem Variação }\') as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        , tblestoquesaldo.codestoquesaldo
                        '
                    )
                );
                $query->orderBy('tblproduto.produto');
                $query->orderBy('variacao');
                break;
            
            case 'produto':
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblproduto.codproduto as coditem
                        , tblproduto.produto as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->groupBy('tblproduto.codproduto');
                $query->groupBy('tblproduto.produto');
                $query->orderBy('produto');
                break;
            
            case 'marca':
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblmarca.codmarca as coditem
                        , tblmarca.marca as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->leftJoin('tblmarca', 'tblmarca.codmarca', '=', 'tblproduto.codmarca');
                $query->groupBy('tblmarca.codmarca');
                $query->groupBy('tblmarca.marca');
                $query->orderBy('marca');
                break;
            
            case 'subgrupoproduto':
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblsubgrupoproduto.codsubgrupoproduto as coditem
                        , tblsubgrupoproduto.subgrupoproduto as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->groupBy('tblsubgrupoproduto.codsubgrupoproduto');
                $query->groupBy('tblsubgrupoproduto.subgrupoproduto');
                $query->orderBy('subgrupoproduto');
                break;
            
            case 'grupoproduto':
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblgrupoproduto.codgrupoproduto as coditem
                        , tblgrupoproduto.grupoproduto as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->groupBy('tblgrupoproduto.codgrupoproduto');
                $query->groupBy('tblgrupoproduto.grupoproduto');
                $query->orderBy('grupoproduto');
                break;
            
            case 'familiaproduto':
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblfamiliaproduto.codfamiliaproduto as coditem
                        , tblfamiliaproduto.familiaproduto as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->groupBy('tblfamiliaproduto.codfamiliaproduto');
                $query->groupBy('tblfamiliaproduto.familiaproduto');
                $query->orderBy('familiaproduto');
                break;
            
            case 'secaoproduto':
            default:
                $query->select(
                    DB::raw(
                        ' sum(saldoquantidade) as saldoquantidade
                        , sum(' . (($valor=='venda')?'saldoquantidade * tblproduto.preco':'saldovalor') . ') as saldovalor
                        , sum(estoqueminimo) as estoqueminimo
                        , sum(estoquemaximo) as estoquemaximo
                        , fiscal
                        , tblsecaoproduto.codsecaoproduto as coditem
                        , tblsecaoproduto.secaoproduto as item
                        , tblestoquelocal.codestoquelocal
                        , tblestoquelocal.estoquelocal
                        '
                    )
                );
                $query->groupBy('tblsecaoproduto.codsecaoproduto');
                $query->groupBy('tblsecaoproduto.secaoproduto');
                $query->leftJoin('tblsecaoproduto', 'tblsecaoproduto.codsecaoproduto', '=', 'tblfamiliaproduto.codsecaoproduto');
                $query->orderBy('secaoproduto');
                break;
        }
        
        $query->orderBy('tblestoquelocal.codestoquelocal');
        
        if (!empty($filtro['codsecaoproduto'])) {
            $query->where('tblfamiliaproduto.codsecaoproduto', '=', $filtro['codsecaoproduto']);
        }
        
        if (!empty($filtro['codestoquelocal'])) {
            $query->where('tblestoquelocalprodutovariacao.codestoquelocal', '=', $filtro['codestoquelocal']);
        }

        if (!empty($filtro['codfamiliaproduto'])) {
            $query->where('tblgrupoproduto.codfamiliaproduto', '=', $filtro['codfamiliaproduto']);
        }

        if (!empty($filtro['codproduto'])) {
            $query->where('tblprodutovariacao.codproduto', '=', $filtro['codproduto']);
        }

        if (!empty($filtro['codprodutovariacao'])) {
            $query->where('tblestoquelocalprodutovariacao.codprodutovariacao', '=', $filtro['codprodutovariacao']);
        }

        if (!empty($filtro['codgrupoproduto'])) {
            $query->where('tblsubgrupoproduto.codgrupoproduto', '=', $filtro['codgrupoproduto']);
        }

        if (!empty($filtro['codsubgrupoproduto'])) {
            $query->where('tblproduto.codsubgrupoproduto', '=', $filtro['codsubgrupoproduto']);
        }

        if (!empty($filtro['corredor'])) {
            $query->where('tblestoquelocalprodutovariacao.corredor', '=', $filtro['corredor']);
        }

        if (!empty($filtro['prateleira'])) {
            $query->where('tblestoquelocalprodutovariacao.prateleira', '=', $filtro['prateleira']);
        }

        if (!empty($filtro['coluna'])) {
            $query->where('tblestoquelocalprodutovariacao.coluna', '=', $filtro['coluna']);
        }

        if (!empty($filtro['bloco'])) {
            $query->where('tblestoquelocalprodutovariacao.bloco', '=', $filtro['bloco']);
        }

        if (!empty($filtro['codmarca'])) {
            $query->where(function ($q2) use($filtro) {
                $q2->orWhere('tblproduto.codmarca', '=', $filtro['codmarca']);
                $q2->orWhere('tblprodutovariacao.codmarca', '=', $filtro['codmarca']);                        
            });
        }

        if (!empty($filtro['saldo']) || !empty($filtro['minimo']) || !empty($filtro['maximo'])) {
            
            $query->whereIn('tblestoquesaldo.codestoquelocalprodutovariacao', function($q2) use ($filtro){
                
                $q2->select('tblestoquesaldo.codestoquelocalprodutovariacao')
                    ->from('tblestoquesaldo')
                    ->join('tblestoquelocalprodutovariacao', 'tblestoquelocalprodutovariacao.codestoquelocalprodutovariacao', '=', 'tblestoquesaldo.codestoquelocalprodutovariacao')
                    ->whereRaw('fiscal = false');
                
                if (!empty($filtro['minimo'])) {
                    if ($filtro['minimo'] == -1) {
                        $q2->whereRaw('saldoquantidade < estoqueminimo');
                    } else if ($filtro['minimo'] == 1) {
                        $q2->whereRaw('saldoquantidade >= estoqueminimo');
                        
                    }
                }
                
                if (!empty($filtro['maximo'])) {
                    if ($filtro['maximo'] == -1) {
                        $q2->whereRaw('saldoquantidade <= estoquemaximo');
                    } else if ($filtro['maximo'] == 1) {
                        $q2->whereRaw('saldoquantidade > estoquemaximo');
                    }
                }
                
                if (!empty($filtro['saldo'])) {
                    if ($filtro['saldo'] == -1) {
                        $q2->whereRaw('saldoquantidade < 0');
                    } else if ($filtro['saldo'] == 1) {
                        $q2->whereRaw('saldoquantidade > 0');
                        
                    }
                }
                
            });
        }

        $query->whereRaw('tblestoquesaldo.saldoquantidade != 0');
        
        $rows = $query->get();
        
        $ret = [];
        
        $total = [
            'coditem' => null,
            'item' => null,
            'estoquelocal' => [
                'total' => [
                    'estoqueminimo' => null,
                    'estoquemaximo' => null,
                    'fisico' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ],
                    'fiscal' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ]                    
                ]
            ]
        ];
                
        foreach($rows as $row) {

            if (!isset($ret[$row->coditem])) {
                $ret[$row->coditem] = [
                    'coditem' => $row->coditem,
                    'item' => $row->item,
                    'estoquelocal' => [
                        'total' => [
                            'estoqueminimo' => null,
                            'estoquemaximo' => null,                            
                            'fisico' => [
                                'saldoquantidade' => null,
                                'saldovalor' => null,
                            ],
                            'fiscal' => [
                                'saldoquantidade' => null,
                                'saldovalor' => null,
                            ]
                        ]
                    ]
                ];
            }

            if (!isset($ret[$row->coditem]['estoquelocal'][$row->codestoquelocal])) {
                $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal] = [
                    'codestoquelocal' => $row->codestoquelocal,
                    'estoquelocal' => $row->estoquelocal,
                    'estoqueminimo' => null,
                    'estoquemaximo' => null,
                    'fisico' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ],
                    'fiscal' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ]
                ];
            }
            
            if (!isset($total['estoquelocal'][$row->codestoquelocal])) {
                $total['estoquelocal'][$row->codestoquelocal] = [
                    'codestoquelocal' => $row->codestoquelocal,
                    'estoquelocal' => $row->estoquelocal,
                    'estoqueminimo' => null,
                    'estoquemaximo' => null,
                    'fisico' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ],
                    'fiscal' => [
                        'saldoquantidade' => null,
                        'saldovalor' => null,
                    ]                    
                ];
            }
            
            if  (empty($ret[$row->coditem]['estoquelocal'][$row->codestoquelocal]['estoqueminimo'])) {
                $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal]['estoqueminimo'] = $row->estoqueminimo;
                $ret[$row->coditem]['estoquelocal']['total']['estoqueminimo'] += $row->estoqueminimo;
                $total['estoquelocal'][$row->codestoquelocal]['estoqueminimo'] += $row->estoqueminimo;
                $total['estoquelocal']['total']['estoqueminimo'] += $row->estoqueminimo;
            }
            
            if  (empty($ret[$row->coditem]['estoquelocal'][$row->codestoquelocal]['estoquemaximo'])) {
                $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal]['estoquemaximo'] = $row->estoquemaximo;
                $ret[$row->coditem]['estoquelocal']['total']['estoquemaximo'] += $row->estoquemaximo;
                $total['estoquelocal'][$row->codestoquelocal]['estoquemaximo'] += $row->estoquemaximo;
                $total['estoquelocal']['total']['estoquemaximo'] += $row->estoquemaximo;
            }
            
            $fiscal = ($row->fiscal)?'fiscal':'fisico';
            
            $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal][$fiscal]['saldoquantidade'] = $row->saldoquantidade;
            $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal][$fiscal]['saldovalor'] = $row->saldovalor;
            
            if (!empty($row->codestoquesaldo)) {
                $ret[$row->coditem]['estoquelocal'][$row->codestoquelocal][$fiscal]['codestoquesaldo'] = $row->codestoquesaldo;
            }
            
            $ret[$row->coditem]['estoquelocal']['total'][$fiscal]['saldoquantidade'] += $row->saldoquantidade;
            $ret[$row->coditem]['estoquelocal']['total'][$fiscal]['saldovalor'] += $row->saldovalor;
            
            $total['estoquelocal'][$row->codestoquelocal][$fiscal]['saldoquantidade'] += $row->saldoquantidade;
            $total['estoquelocal'][$row->codestoquelocal][$fiscal]['saldovalor'] += $row->saldovalor;

            $total['estoquelocal']['total'][$fiscal]['saldoquantidade'] += $row->saldoquantidade;
            $total['estoquelocal']['total'][$fiscal]['saldovalor'] += $row->saldovalor;

        }
        
        $ret['total'] = $total;
        
        return $ret;
    }
    
    public static function relatorioAnalise($filtro) 
    {
        
        // Monta tabelas da Query
        $qry = DB::table('tblproduto as p');
        $qry->join('tblprodutovariacao as pv', 'pv.codproduto', '=', 'p.codproduto');
        $qry->leftJoin('tblmarca as m', function($join) {
            $join->on('m.codmarca', '=', DB::raw('coalesce(pv.codmarca, p.codmarca)'));

        });
        $qry->leftJoin('tblunidademedida as um', 'um.codunidademedida', '=', 'p.codunidademedida');
        $qry->leftJoin('tblsubgrupoproduto as sgp', 'sgp.codsubgrupoproduto', '=', 'p.codsubgrupoproduto');
        $qry->leftJoin('tblgrupoproduto as gp', 'gp.codgrupoproduto', '=', 'sgp.codgrupoproduto');
        $qry->leftJoin('tblfamiliaproduto as fp', 'fp.codfamiliaproduto', '=', 'gp.codfamiliaproduto');
        $qry->leftJoin('tblsecaoproduto as sp', 'sp.codsecaoproduto', '=', 'fp.codsecaoproduto');
        $qry->leftJoin('tblestoquelocalprodutovariacao as elpv', 'elpv.codprodutovariacao', '=', 'pv.codprodutovariacao');
        $qry->leftJoin('tblestoquelocal as el', 'el.codestoquelocal', '=', 'elpv.codestoquelocal');
        $qry->leftJoin('tblestoquesaldo as es', function($join) {
            $join->on('es.codestoquelocalprodutovariacao', '=', 'elpv.codestoquelocalprodutovariacao');
            $join->on('es.fiscal', '=', DB::RAW('false'));

        });
        
        // Monta campos Selecionados
        $qry->select([
            'p.codproduto', 
            'p.produto', 
            'p.preco',
            'p.referencia',

            'pv.codprodutovariacao',
            'pv.variacao',
            'pv.referencia as referenciavariacao',
            'pv.dataultimacompra',
            'pv.quantidadeultimacompra',
            'pv.custoultimacompra',
            
            'm.codmarca', 
            'm.marca',
            
            'um.codunidademedida',
            'um.sigla as siglaunidademedida', 
            
            'sgp.codsubgrupoproduto',
            'sgp.subgrupoproduto',
            
            'gp.codgrupoproduto',
            'gp.grupoproduto',

            'fp.codfamiliaproduto',
            'fp.familiaproduto',
            
            'sp.codsecaoproduto',
            'sp.secaoproduto',

            'elpv.codestoquelocalprodutovariacao',
            'elpv.corredor',
            'elpv.prateleira',
            'elpv.coluna',
            'elpv.bloco',
            'elpv.estoqueminimo',
            'elpv.estoquemaximo',
            'elpv.vendabimestrequantidade',
            'elpv.vendasemestrequantidade',
            'elpv.vendaanoquantidade',
            'elpv.vendadiaquantidadeprevisao',

            'el.codestoquelocal',
            'el.sigla as siglaestoquelocal',

            'es.codestoquesaldo',
            'es.dataentrada',
            'es.saldoquantidade',
            'es.saldovalor',
            'es.customedio',
        ]);
        
        // Aplica Filtro
        if (!empty($filtro['codproduto'])) {
            $qry->where('p.codproduto', $filtro['codproduto']);
        }

        if (!empty($filtro['codmarca'])) {
            $qry->where('m.codmarca', $filtro['codmarca']);
        }

        if (!empty($filtro['codsubgrupoproduto'])) {
            $qry->where('p.codsubgrupoproduto', $filtro['codsubgrupoproduto']);
        }
        
        if (!empty($filtro['codgrupoproduto'])) {
            $qry->where('sgp.codgrupoproduto', $filtro['codgrupoproduto']);
        }
        
        if (!empty($filtro['codfamiliaproduto'])) {
            $qry->where('gp.codfamiliaproduto', $filtro['codfamiliaproduto']);
        }
        
        if (!empty($filtro['codsecaoproduto'])) {
            $qry->where('fp.codsecaoproduto', $filtro['codsecaoproduto']);
        }
        
        switch (isset($filtro['ativo'])?$filtro['ativo']:'9') {
            case 1: //Ativos
                $qry->whereNull('p.inativo');
                break;
            case 2: //Inativos
                $qry->whereNotNull('p.inativo');
                break;
            case 9; //Todos
            default:
        }
        
        if (!empty($filtro['codestoquelocal'])) {
            $qry->where('elpv.codestoquelocal', '=', $filtro['codestoquelocal']);
        }

        if (!empty($filtro['corredor'])) {
            $qry->where('elpv.corredor', '=', $filtro['corredor']);
        }

        if (!empty($filtro['prateleira'])) {
            $qry->where('elpv.prateleira', '=', $filtro['prateleira']);
        }

        if (!empty($filtro['coluna'])) {
            $qry->where('elpv.coluna', '=', $filtro['coluna']);
        }

        if (!empty($filtro['bloco'])) {
            $qry->where('elpv.bloco', '=', $filtro['bloco']);
        }

        if (!empty($filtro['minimo'])) {
            if ($filtro['minimo'] == -1) {
                $qry->whereRaw('es.saldoquantidade < elpv.estoqueminimo');
            } else if ($filtro['minimo'] == 1) {
                $qry->whereRaw('es.saldoquantidade >= elpv.estoqueminimo');

            }
        }

        if (!empty($filtro['maximo'])) {
            if ($filtro['maximo'] == -1) {
                $qry->whereRaw('es.saldoquantidade <= elpv.estoquemaximo');
            } else if ($filtro['maximo'] == 1) {
                $qry->whereRaw('es.saldoquantidade > elpv.estoquemaximo');
            }
        }

        if (!empty($filtro['saldo'])) {
            if ($filtro['saldo'] == -1) {
                $qry->whereRaw('es.saldoquantidade < 0');
            } else if ($filtro['saldo'] == 1) {
                $qry->whereRaw('es.saldoquantidade > 0');
            }
        }
        
        // Ordenacao
        switch ($filtro['agrupamento']) {
            
            case 'marca':
                $campo_codigo = 'codmarca';
                $campos_descricao = [
                    'marca'
                ];
                $qry->orderBy('m.marca', 'ASC');
                $qry->orderBy('m.codmarca', 'ASC');
                break;
            
            case 'subgrupoproduto';
            default:
                $campo_codigo = 'codsubgrupoproduto';
                $campos_descricao = [
                    'secaoproduto',
                    'familiaproduto',
                    'grupoproduto',
                    'subgrupoproduto',
                ];
                $qry->orderBy('sp.secaoproduto', 'ASC');
                $qry->orderBy('sp.codsecaoproduto', 'ASC');
                $qry->orderBy('fp.familiaproduto', 'ASC');
                $qry->orderBy('fp.codfamiliaproduto', 'ASC');
                $qry->orderBy('gp.grupoproduto', 'ASC');
                $qry->orderBy('gp.codgrupoproduto', 'ASC');
                $qry->orderBy('sgp.subgrupoproduto', 'ASC');
                $qry->orderBy('sgp.codsubgrupoproduto', 'ASC');
                $qry->orderBy('m.codmarca', 'ASC');
                break;
            
        }
        $qry->orderBy('p.produto', 'ASC');
        $qry->orderBy('p.codproduto', 'ASC');
        $qry->orderByRaw('pv.variacao ASC NULLS FIRST');
        $qry->orderBy('pv.codprodutovariacao', 'ASC');
        $qry->orderBy('el.codestoquelocal', 'ASC');
        
        // Busca Registros
        $registros = collect($qry->get());
        //dd($registros);
        
        $ret = [
            'agrupamentos' => [],
            'filtro' => $filtro,
        ];
        foreach ($registros as $registro) {
            
            $codigo = $registro->$campo_codigo;
            
            // Agrupamento Principal
            if (!isset($ret['agrupamentos'][$codigo])) {
                
                $descricao = [];
                foreach ($campos_descricao as $token) {
                    $descricao[] = $registro->{$token};
                }
                $descricao = implode(' / ', $descricao);

                $ret['agrupamentos'][$codigo] = [
                    'codigo' => $codigo,
                    'descricao' => $descricao,
                ];
            }
            
            // Agrupamento Produto
            if (!isset($ret['agrupamentos'][$codigo]['produtos'][$registro->codproduto])) {
                $ret['agrupamentos'][$codigo]['produtos'][$registro->codproduto] = [
                    'codproduto' => $registro->codproduto,
                    'produto' => $registro->produto,
                    'preco' => $registro->preco,
                    'siglaunidademedida' => $registro->siglaunidademedida,
                    'codmarca' => $registro->codmarca,
                    'marca' => $registro->marca,
                ];
            }
            
            // Agrupamento Variacao
            if (!isset($ret['agrupamentos'][$codigo]['produtos'][$registro->codproduto]['variacoes'][$registro->codprodutovariacao])) {
                $ret['agrupamentos'][$codigo]['produtos'][$registro->codproduto]['variacoes'][$registro->codprodutovariacao] = [
                    'codprodutovariacao' => $registro->codprodutovariacao,
                    'variacao' => $registro->variacao,
                    'referencia' => $registro->referencia,
                    'dataultimacompra' => !empty($registro->dataultimacompra)?new Carbon($registro->dataultimacompra):null,
                    'custoultimacompra' => $registro->custoultimacompra,
                    'quantidadeultimacompra' => $registro->quantidadeultimacompra,
                    'locais' => [],
                ];
            }
            
            // Agrupamento Local Estoque
            if (!empty($registro->codestoquelocal)) {
                //dd($registro);
                $saldodias = null;
                $vendaprevisaoquinzena = null;
                if (!empty($registro->vendadiaquantidadeprevisao)) {
                    $saldodias = floor($registro->saldoquantidade / $registro->vendadiaquantidadeprevisao);
                    $vendaprevisaoquinzena = round($registro->vendadiaquantidadeprevisao * 15, 0);
                }
                $ret['agrupamentos'][$codigo]['produtos'][$registro->codproduto]['variacoes'][$registro->codprodutovariacao]['locais'][$registro->codestoquelocal] = [
                    'codestoquelocal' => $registro->codestoquelocal,
                    'siglaestoquelocal' => $registro->siglaestoquelocal,
                    'corredor' => $registro->corredor,
                    'prateleira' => $registro->prateleira,
                    'coluna' => $registro->coluna,
                    'bloco' => $registro->bloco,
                    'codestoquesaldo' => $registro->codestoquesaldo,
                    'saldoquantidade' => $registro->saldoquantidade,
                    'saldovalor' => $registro->saldovalor,
                    'saldodias' => $saldodias,
                    'customedio' => $registro->customedio,
                    'dataentrada' => !empty($registro->dataentrada)?new Carbon($registro->dataentrada):null,
                    'estoqueminimo' => $registro->estoqueminimo,
                    'estoquemaximo' => $registro->estoquemaximo,
                    'vendabimestrequantidade' => $registro->vendabimestrequantidade,
                    'vendasemestrequantidade' => $registro->vendasemestrequantidade,
                    'vendaanoquantidade' => $registro->vendaanoquantidade,
                    'vendaprevisaoquinzena' => $vendaprevisaoquinzena,
                    'vendadiaquantidadeprevisao' => $registro->vendadiaquantidadeprevisao,
                ];
            }
            
        }
        
        foreach ($ret['agrupamentos'] as $codigo => $agrupamento) {
            
            foreach ($agrupamento['produtos'] as $codproduto => $produto) {
                
                foreach ($produto['variacoes'] as $codprodutovariacao => $variacao) {
                    
                    // Totaliza Variacao
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldoquantidade'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'saldoquantidade')); 
                    
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldovalor'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'saldovalor')); 
                    
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['customedio'] = null;
                    if (!empty($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldoquantidade'])) {
                        $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['customedio'] =
                            $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldovalor'] /
                            $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldoquantidade'];
                    }
                    
                    $vendadiaquantidadeprevisao =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'vendadiaquantidadeprevisao')); 
                    $saldodias = null;
                    $vendaprevisaoquinzena = null;
                    if (!empty($vendadiaquantidadeprevisao)) {
                        $saldodias = floor($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldoquantidade'] / $vendadiaquantidadeprevisao);
                        $vendaprevisaoquinzena = round($vendadiaquantidadeprevisao * 15, 0);
                    }
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['vendadiaquantidadeprevisao'] = $vendadiaquantidadeprevisao;
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['saldodias'] = $saldodias;
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['vendaprevisaoquinzena'] = $vendaprevisaoquinzena;

                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['estoqueminimo'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'estoqueminimo')); 

                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['estoquemaximo'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'estoquemaximo')); 

                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['vendabimestrequantidade'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'vendabimestrequantidade')); 

                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['vendasemestrequantidade'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'vendasemestrequantidade')); 

                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['vendaanoquantidade'] =
                        array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'][$codprodutovariacao]['locais'], 'vendaanoquantidade')); 
                    
                }
                
                // Totaliza Produto
                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldoquantidade'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'saldoquantidade')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldovalor'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'saldovalor')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['customedio'] = null;
                if (!empty($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldoquantidade'])) {
                    $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['customedio'] =
                        $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldovalor'] /
                        $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldoquantidade'];
                }

                $vendadiaquantidadeprevisao =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'vendadiaquantidadeprevisao')); 
                $saldodias = null;
                $vendaprevisaoquinzena = null;
                if (!empty($vendadiaquantidadeprevisao)) {
                    $saldodias = floor($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldoquantidade'] / $vendadiaquantidadeprevisao);
                    $vendaprevisaoquinzena = round($vendadiaquantidadeprevisao * 15, 0);
                }
                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['vendadiaquantidadeprevisao'] = $vendadiaquantidadeprevisao;
                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['saldodias'] = $saldodias;
                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['vendaprevisaoquinzena'] = $vendaprevisaoquinzena;

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['estoqueminimo'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'estoqueminimo')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['estoquemaximo'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'estoquemaximo')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['vendabimestrequantidade'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'vendabimestrequantidade')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['vendasemestrequantidade'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'vendasemestrequantidade')); 

                $ret['agrupamentos'][$codigo]['produtos'][$codproduto]['vendaanoquantidade'] =
                    array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'][$codproduto]['variacoes'], 'vendaanoquantidade')); 
                
            }
            
            // Totaliza Agrupamento
            $ret['agrupamentos'][$codigo]['saldoquantidade'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'saldoquantidade')); 

            $ret['agrupamentos'][$codigo]['saldovalor'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'saldovalor')); 

            $ret['agrupamentos'][$codigo]['customedio'] = null;
            if (!empty($ret['agrupamentos'][$codigo]['saldoquantidade'])) {
                $ret['agrupamentos'][$codigo]['customedio'] =
                    $ret['agrupamentos'][$codigo]['saldovalor'] /
                    $ret['agrupamentos'][$codigo]['saldoquantidade'];
            }

            $vendadiaquantidadeprevisao =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'vendadiaquantidadeprevisao')); 
            $saldodias = null;
            $vendaprevisaoquinzena = null;
            if (!empty($vendadiaquantidadeprevisao)) {
                $saldodias = floor($ret['agrupamentos'][$codigo]['saldoquantidade'] / $vendadiaquantidadeprevisao);
                $vendaprevisaoquinzena = round($vendadiaquantidadeprevisao * 15, 0);
            }
            $ret['agrupamentos'][$codigo]['vendadiaquantidadeprevisao'] = $vendadiaquantidadeprevisao;
            $ret['agrupamentos'][$codigo]['saldodias'] = $saldodias;
            $ret['agrupamentos'][$codigo]['vendaprevisaoquinzena'] = $vendaprevisaoquinzena;

            $ret['agrupamentos'][$codigo]['estoqueminimo'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'estoqueminimo')); 

            $ret['agrupamentos'][$codigo]['estoquemaximo'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'estoquemaximo')); 

            $ret['agrupamentos'][$codigo]['vendabimestrequantidade'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'vendabimestrequantidade')); 

            $ret['agrupamentos'][$codigo]['vendasemestrequantidade'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'vendasemestrequantidade')); 

            $ret['agrupamentos'][$codigo]['vendaanoquantidade'] =
                array_sum(array_column($ret['agrupamentos'][$codigo]['produtos'], 'vendaanoquantidade')); 
                
        }
        
        // Totaliza Relatorio
        $ret['saldoquantidade'] =
            array_sum(array_column($ret['agrupamentos'], 'saldoquantidade')); 

        $ret['saldovalor'] =
            array_sum(array_column($ret['agrupamentos'], 'saldovalor')); 

        $ret['customedio'] = null;
        if (!empty($ret['saldoquantidade'])) {
            $ret['customedio'] =
                $ret['saldovalor'] /
                $ret['saldoquantidade'];
        }

        $vendadiaquantidadeprevisao =
            array_sum(array_column($ret['agrupamentos'], 'vendadiaquantidadeprevisao')); 
        $saldodias = null;
        $vendaprevisaoquinzena = null;
        if (!empty($vendadiaquantidadeprevisao)) {
            $saldodias = floor($ret['saldoquantidade'] / $vendadiaquantidadeprevisao);
            $vendaprevisaoquinzena = round($vendadiaquantidadeprevisao * 15, 0);
        }
        $ret['vendadiaquantidadeprevisao'] = $vendadiaquantidadeprevisao;
        $ret['saldodias'] = $saldodias;
        $ret['vendaprevisaoquinzena'] = $vendaprevisaoquinzena;

        $ret['estoqueminimo'] =
            array_sum(array_column($ret['agrupamentos'], 'estoqueminimo'));

        $ret['estoquemaximo'] =
            array_sum(array_column($ret['agrupamentos'], 'estoquemaximo'));

        $ret['vendabimestrequantidade'] =
            array_sum(array_column($ret['agrupamentos'], 'vendabimestrequantidade'));

        $ret['vendasemestrequantidade'] =
            array_sum(array_column($ret['agrupamentos'], 'vendasemestrequantidade'));

        $ret['vendaanoquantidade'] =
            array_sum(array_column($ret['agrupamentos'], 'vendaanoquantidade'));
        
        return $ret;
    }
}
