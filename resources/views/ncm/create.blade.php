@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default" id="submenu">
    <div class="container-fluid"> 
        <ul class="nav navbar-nav">
            <li><a href="<?php echo url("ncm");?>"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a></li>
        </ul>
    </div>
</nav>
<ol class="breadcrumb header">
{!! 
    titulo(
        null,
        [
            url("ncm") => 'NCM',
            'Nova NCM',
        ],
        $model->inativo
    ) 
!!}
</ol>
<hr>
<br>
{!! Form::model($model, ['method' => 'POST', 'class' => 'form-horizontal', 'id' => 'form-ncm', 'route' => 'ncm.store']) !!}
    @include('errors.form_error')
    @include('ncm.form', ['submitTextButton' => 'Salvar'])
 {!! Form::close() !!}   
@stop