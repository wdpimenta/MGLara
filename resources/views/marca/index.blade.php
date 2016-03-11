@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default navbar-fixed-top" id="submenu">
  <div class="container-fluid"> 
    <ul class="nav navbar-nav">
    <!--
    <li>
        <a href="{{ url('marca')}}"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a>
    </li> 
    -->
    </ul>
  </div>
</nav>
<h1 class="header">Marcas</h1>
<div class="marcas-pagination pull-right">{!! $model->appends(Request::all())->render() !!}</div>
<hr>
<?php

//dd($ess);

foreach($ess as $es)
{
    $arr_saldos[$es->codmarca][$es->codestoquelocal][$es->fiscal] = [
        'saldoquantidade' => $es->saldoquantidade,
        'saldovalor' => $es->saldovalor,
    ];
    
    if (!isset($arr_totais[$es->codestoquelocal][$es->fiscal]))
        $arr_totais[$es->codestoquelocal][$es->fiscal] = [
            'saldoquantidade' => 0,
            'saldovalor' => 0
        ];
    
    $arr_totais[$es->codestoquelocal][$es->fiscal]['saldoquantidade'] += $es->saldoquantidade;
    $arr_totais[$es->codestoquelocal][$es->fiscal]['saldovalor'] += $es->saldovalor;
}

//dd($arr_saldos);
?>

<table class="table table-striped table-condensed table-hover table-bordered">
    <thead>
        <th colspan="2">
            Marcas
        </th>
        @foreach ($els as $el)
        <th colspan='2' class='text-center' style='border-left-width: 2px'>
            {{ $el->estoquelocal }}
        </th>
        @endforeach
    </thead>
    
    <tbody>
        @foreach($model as $row)
        <tr>
            <th rowspan="2">
                <a href="{{ url("marca/$row->codmarca") }}">{{$row->marca}}</a>
            </th>
            <th>
                Físico
            </th>
            @foreach ($els as $el)
            <td class='text-right' style='border-left-width: 2px'>
                @if (isset($arr_saldos[$row->codmarca][$el->codestoquelocal][0]))
                    {{ formataNumero($arr_saldos[$row->codmarca][$el->codestoquelocal][0]['saldoquantidade'], 0) }}
                @endif
            </td>
            <td class='text-right'>
                @if (isset($arr_saldos[$row->codmarca][$el->codestoquelocal][0]))
                    {{ formataNumero($arr_saldos[$row->codmarca][$el->codestoquelocal][0]['saldovalor'], 2) }}
                @endif
            </td>
            @endforeach
        </tr>
        <tr>
            <th>
                Fiscal
            </th>
            @foreach ($els as $el)
            <td class='text-right' style='border-left-width: 2px'>
                @if (isset($arr_saldos[$row->codmarca][$el->codestoquelocal][1]))
                    {{ formataNumero($arr_saldos[$row->codmarca][$el->codestoquelocal][1]['saldoquantidade'], 0) }}
                @endif
            </td>
            <td class='text-right'>
                @if (isset($arr_saldos[$row->codmarca][$el->codestoquelocal][1]))
                    {{ formataNumero($arr_saldos[$row->codmarca][$el->codestoquelocal][1]['saldovalor'], 2) }}
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th rowspan="2">
                Totais
            </th>
            <th>
                Físico
            </th>
            @foreach ($els as $el)
            <th class='text-right' style='border-left-width: 2px'>
                @if (isset($arr_totais[$el->codestoquelocal][0]))
                    {{ formataNumero($arr_totais[$el->codestoquelocal][0]['saldoquantidade'], 0) }}
                @endif
            </th>
            <th class='text-right'>
                @if (isset($arr_totais[$el->codestoquelocal][0]))
                    {{ formataNumero($arr_totais[$el->codestoquelocal][0]['saldovalor'], 2) }}
                @endif
            </th>
            @endforeach
        </tr>
        <tr>
            <th>
                Fiscal
            </th>
            @foreach ($els as $el)
            <th class='text-right' style='border-left-width: 2px'>
                @if (isset($arr_totais[$el->codestoquelocal][1]))
                    {{ formataNumero($arr_totais[$el->codestoquelocal][1]['saldoquantidade'], 0) }}
                @endif
            </th>
            <th class='text-right'>
                @if (isset($arr_totais[$el->codestoquelocal][1]))
                    {{ formataNumero($arr_totais[$el->codestoquelocal][1]['saldovalor'], 2) }}
                @endif
            </th>
            @endforeach
        </tr>
    </tfoot>
</table>

@if (count($model) === 0)
    <h3>Nenhum registro encontrado!</h3>
@endif    

@section('inscript')
<style type="text/css">
    ul.pagination {
        margin: 0;
    }
</style>
<script type="text/javascript">
  $(document).ready(function() {
    $('ul.pagination').removeClass('hide');
  });
</script>
@endsection
@stop