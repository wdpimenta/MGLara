@extends('layouts.default')
@section('content')
<ol class="breadcrumb header">
    {!! 
        titulo(
            null,
            [
                url("imagem") => 'Imagens',
                'Lixeira'
            ],
            null
        ) 
    !!}    
    <li class='active'>
        <small>
            <a title="Esvaziar Lixeira" id="esvaziar-lixeira" href="{{ url('imagem/esvaziar-lixeira') }}"><i class="glyphicon glyphicon-trash"></i></a>
        </small>
    </li>      
    
</ol>
<div id="registros">
    <div id="imagens" class="row">
    @foreach($model as $row)
        <div class="imagem-grid-item col-xs-2">
            <a href="{{ url("imagem/{$row->codimagem}") }}" class="thumbnail">
                <img src="<?php echo URL::asset('public/imagens/'.$row->observacoes);?>" class="img-responsive">
            </a>
        </div>          
    @endforeach
    @if (count($model) === 0)
        <div class="col-md-12">
            <h3>Nenhuma imagem na lixeira!</h3>
        </div>
    @endif    
  </div>
  <?php echo $model->appends(Request::all())->render();?>
</div>
@section('inscript')
<style type="text/css">
.img-responsive {
    height: 115px !important;
}
.thumbnail.inativo {
    border: 1px solid #c4170c;
}
</style>
<script type="text/javascript">
$(document).ready(function() {
    var loading_options = {
        finishedMsg: "<div class='end-msg'>Fim dos registros</div>",
        msgText: "<div class='center'>Carregando mais itens...</div>",
        img: 'public/img/listagem-json-loader.gif'
    };
    $('#imagens').infinitescroll({
        loading : loading_options,
        navSelector : "#registros .pagination",
        nextSelector : "#registros .pagination li.active + li a",
        itemSelector : "#imagens div.imagem-grid-item"
    });    
    
    $('#esvaziar-lixeira').click(function (e) {
        e.preventDefault();
        var url = $('#esvaziar-lixeira').attr('href');
        bootbox.confirm("Tem certeza que deseja deletar todas essas imagens", function(result) {
            if (result) {
                window.location.href = url;
            }
        }); 
    });
    
});  
</script>
@endsection
@stop