@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default navbar-fixed-top" id="submenu">
    <div class="container-fluid"> 
        <ul class="nav navbar-nav">
            <li>
                <a href="{{ url("ncm/create") }}"><span class="glyphicon glyphicon-plus"></span> Nova</a>
            </li> 
        </ul>
    </div>
</nav>
<h1 class="header">
    {!! titulo(null, 'NCM', null) !!}
    <a class="btn btn-primary pull-right" role="button" data-toggle="collapse" href="#div-filtro" aria-expanded="false" aria-controls="div-filtro">
        <span class='glyphicon glyphicon-search'></span>
    </a>    
</h1>
<div class="clearfix"></div>
<div class='collapse' id='div-filtro'>
    <div class='well well-sm' style="padding:9px 0">
    {!! Form::model(
        Request::session()->get('ncm.index'), 
        [
            'route' => 'ncm.index', 
            'method' => 'GET', 
            'class' => 'form-horizontal', 
            'id' => 'ncm-search', 
            'role' => 'search', 
            'autocomplete' => 'off'
        ]
    )!!}
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('codncm', '#', ['class' => 'col-sm-2 control-label']) !!}
                <div class="col-md-8">{!! Form::text('codncm', null, ['class' => 'form-control', 'placeholder' => '#']) !!}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('ncm', 'NCM', ['class' => 'col-sm-2 control-label']) !!}
                <div class="col-md-10">{!! Form::text('ncm', null, ['class' => 'form-control', 'placeholder' => 'NCM']) !!}</div>
            </div>
        </div>
        <div class="col-md-2">      
            <div class="form-group">
                <div class="col-md-offset-2 col-md-10">
                    <button type="submit" class="btn btn-default"><i class="glyphicon glyphicon-search"></i> Buscar</button>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>
    {!! Form::close() !!}
</div>
<div id="registros" class="row">
    <div class="col-md-5">
        <div class="list-group list-group-striped list-group-hover" id="items">
          @foreach($model as $row)
            <a class="list-group-item{{ $row->codncm == Request::get('codncm') ? ' active' : '' }}" href="{{ url("ncm/?codncm=$row->codncm") }}">
                {{ $row->ncm }} › {{ $row->descricao }}
            </a>
          @endforeach
          @if (count($model) === 0)
              <h3>Nenhum registro encontrado!</h3>
          @endif    
        </div>
    </div>
    <div class="col-md-7">
        <?php function listaArvoreNcm ($ncms, $id = "") { ?>
            <ul id='{{$id}}'>
            <?php foreach ($ncms as $ncm) { ?>
                <li>
                    <span>{{ formataNcm($ncm->ncm) }} </span>
                    <a href="{{ url("ncm/$ncm->codncm") }}">{{ $ncm->descricao }}</a>
                    <?php if (sizeof($ncm->NcmS) > 0): ?>
                        <?php listaArvoreNcm($ncm->NcmS, null);?>
                    <?php endif; ?>
                </li>
            <?php }?>
            </ul>
        <?php } ?>
        @if(Request::get('codncm'))
        <h1 style="margin-top: 0">{{$ncms->ncm}} - {{ $ncms->descricao }}</h1>
            <?php listaArvoreNcm($ncms->NcmS, 'tree1'); ?>
        @endif()
    </div>
    {!! $model->appends(Request::session()->get('ncm.index'))->render() !!}
</div>
@section('inscript')
<style type="text/css">
.tree, .tree ul {
    margin:0;
    padding:0;
    list-style:none
}
.tree ul {
    margin-left:1em;
    position:relative;
    margin: 10px 0 0 0;
    left: 5px;
}
.tree ul ul {
    margin-left:.5em
}
.tree ul:before {
    content:"";
    display:block;
    width:0;
    position:absolute;
    top:0;
    bottom:0;
    left:0;
    border-left:1px solid
}
.tree li {
    margin:0 0 15px 0;
    padding:0 1em;
    line-height:1.4em;
    color:#369;
    position:relative
}
.tree li span {
    font-weight:700;
}
.tree ul li:before {
    content:"";
    display:block;
    width:10px;
    height:0;
    border-top:1px solid;
    margin-top:-1px;
    position:absolute;
    top:1em;
    left:0
}
.tree ul li:last-child:before {
    background:#fff;
    height:auto;
    top:1em;
    bottom:0
}
.indicator {
    margin-right:5px;
    cursor: pointer;
}
.tree li a {
    text-decoration: none;
    color:#369;
}
.tree li a:hover {
    text-decoration: underline;
}
.tree li button, .tree li button:active, .tree li button:focus {
    text-decoration: none;
    color:#369;
    border:none;
    background:transparent;
    margin:0px 0px 0px 0px;
    padding:0px 0px 0px 0px;
    outline: 0;
}    
</style>
<script type="text/javascript">

   
function atualizaFiltro()
{
    scroll();
    var frmValues = $("#ncm-search").serialize();
    $.ajax({
        type: 'GET',
        url: baseUrl + '/ncm',
        data: frmValues
    })
    .done(function (data) {
        $('#items').html(jQuery(data).find('#items').html()); 
    })
    .fail(function () {
        console.log('Erro no filtro');
    });

    $('#items').infinitescroll('update', {
        state: {
            currPage: 1,
            isDestroyed: false,
            isDone: false             
        },
        path: ['?page=', '&'+frmValues]
    });
}

function scroll()
{
    var loading_options = {
        finishedMsg: "<div class='end-msg'>Fim dos registros</div>",
        msgText: "<div class='center'>Carregando mais itens...</div>",
        img: baseUrl + '/public/img/ajax-loader.gif'
    };

    $('#items').infinitescroll({
        loading : loading_options,
        navSelector : "#registros .pagination",
        nextSelector : "#registros .pagination li.active + li a",
        itemSelector : "#items div.list-group-item",
    });    
}
$(document).ready(function() {
    $.fn.extend({
        treed: function (o) {

          var openedClass = 'glyphicon-minus-sign';
          var closedClass = 'glyphicon-plus-sign';

          if (typeof o != 'undefined'){
            if (typeof o.openedClass != 'undefined'){
            openedClass = o.openedClass;
            }
            if (typeof o.closedClass != 'undefined'){
            closedClass = o.closedClass;
            }
          };

            //initialize each of the top levels
            var tree = $(this);
            tree.addClass("tree");
            tree.find('li').has("ul").each(function () {
                var branch = $(this); //li with children ul
                branch.prepend("<i class='indicator glyphicon " + closedClass + "'></i>");
                branch.addClass('branch');
                branch.on('click', function (e) {
                    if (this == e.target) {
                        var icon = $(this).children('i:first');
                        icon.toggleClass(openedClass + " " + closedClass);
                        $(this).children().children().toggle();
                    }
                })
                branch.children().children().toggle();
            });
            //fire event from the dynamically added icon
          tree.find('.branch .indicator').each(function(){
            $(this).on('click', function () {
                $(this).closest('li').click();
            });
          });

            //fire event to open branch if the li contains an anchor instead of text
            /*
            tree.find('.branch>a').each(function () {
                $(this).on('click', function (e) {
                    $(this).closest('li').click();
                    e.preventDefault();
                });
            });
            */
           
            //fire event to open branch if the li contains a button instead of text
            tree.find('.branch>button').each(function () {
                $(this).on('click', function (e) {
                    $(this).closest('li').click();
                    e.preventDefault();
                });
            });
        }
    });

    $('#tree1').treed();
    
    scroll();
    $("#ncm-search").on("change", function (event) {
        $('#items').infinitescroll('destroy');
        atualizaFiltro();
    }).on('submit', function (event){
        event.preventDefault();
        $('#items').infinitescroll('destroy');
        atualizaFiltro();
    });        

});
</script>
@endsection
@stop