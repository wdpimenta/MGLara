@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default" id="submenu">
    <div class="container-fluid"> 
        <ul class="nav navbar-nav">
            <li><a href="{{ url('tributacao') }}"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a></li>             
            <li><a href="{{ url('tributacao/create') }}"><span class="glyphicon glyphicon-plus"></span> Novo</a></li>             
            <li><a href="{{ url("tributacao/$model->codtributacao") }}"><span class="glyphicon glyphicon-eye-open"></span> Detalhes</a></li>  
        </ul>
    </div>
</nav>
<ol class="breadcrumb header">Alterar Tributaçao: {{$model->tributacao}}</ol>
<hr>
<br>
{!! Form::model($model, ['method' => 'PATCH', 'class' => 'form-horizontal', 'id' => 'form-tributacao', 'action' => ['TributacaoController@update', $model->codtributacao] ]) !!}
    @include('errors.form_error')
    @include('tributacao.form', ['submitTextButton' => 'Salvar'])
{!! Form::close() !!}
@stop