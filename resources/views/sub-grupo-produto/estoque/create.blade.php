@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default" id="submenu">
    <div class="container-fluid"> 
        <ul class="nav navbar-nav">
            <li><a href="<?php echo url("grupo-produto/$request->codgrupoproduto");?>"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a></li>
        </ul>
    </div>
</nav>
<ol class="breadcrumb header">Novo Sub Grupo Produto</ol>
<hr>
<br>
{!! Form::model($model, ['method' => 'POST', 'class' => 'form-horizontal', 'id' => 'form-sub-grupo-produto', 'route' => ['sub-grupo-produto.store', 'codgrupoproduto' => $request->codgrupoproduto] ]) !!}
    @include('errors.form_error')
    @include('sub-grupo-produto.form', ['submitTextButton' => 'Salvar'])
 {!! Form::close() !!}   
@stop