<?php

namespace MGLara\Http\Controllers;

use Illuminate\Http\Request;

use MGLara\Http\Requests;
use MGLara\Http\Controllers\Controller;

use MGLara\Models\GrupoProduto;
use MGLara\Models\EstoqueSaldo;
use MGLara\Models\EstoqueLocal;

class GrupoProdutoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $model = GrupoProduto::orderBy('grupoproduto')->get();
        $ess = EstoqueSaldo::saldoPorGrupoProduto();
        $els = EstoqueLocal::where('inativo', null)->orderBy('codestoquelocal')->get();
        return view('grupo-produto.index', compact('model', 'ess', 'els'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $model = GrupoProduto::findOrFail($id);
        $ess = EstoqueSaldo::saldoPorSubGrupoProduto($model->codgrupoproduto);
        $els = EstoqueLocal::where('inativo', null)->orderBy('codestoquelocal')->get();
        return view('grupo-produto.show', compact('model', 'ess', 'els'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function buscaCodProduto($id)
    {
        $model = GrupoProduto::findOrFail($id);
        foreach ($model->SubGrupoProdutoS as $sg)
            foreach ($sg->ProdutoS as $prod)
                $arr_codproduto[] = $prod->codproduto;
        echo json_encode($arr_codproduto);        
    }
    
}
