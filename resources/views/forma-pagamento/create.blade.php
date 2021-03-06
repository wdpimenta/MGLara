@extends('layouts.default')
@section('content')
<nav class="navbar navbar-default" id="submenu">
    <div class="container-fluid"> 
        <ul class="nav navbar-nav">
            <li><a href="{{ url("forma-pagamento") }}"><span class="glyphicon glyphicon-list-alt"></span> Listagem</a></li>
        </ul>
    </div>
</nav>
<ol class="breadcrumb header">Nova forma de pagamento</ol>
<hr>
<br>
{!! Form::model($model, ['method' => 'POST', 'class' => 'form-horizontal', 'id' => 'form-forma-pagamento', 'route' => 'forma-pagamento.store']) !!}
    @include('errors.form_error')
    @include('forma-pagamento.form', ['submitTextButton' => 'Salvar'])
 {!! Form::close() !!}   
@stop