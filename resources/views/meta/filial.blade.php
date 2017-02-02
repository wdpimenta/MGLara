<h3>Total de vendas</h3>
<div class="panel panel-default">            
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Filial</th>
                <th>Sub-Gerente</th>
                <th class="text-right">Meta</th>
                <th class="text-right">Meta Vendedor</th>
                <th class="text-right">Vendas</th>
                <th class="text-right">Falta</th>
                <th class="text-right">Comissão</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filiais as $filial)
            <tr>
                <td scope="row">{{ $filial['filial'] }}</td>
                <td>
                    <a href="{{ url('pessoa/'.$filial['codpessoa']) }}">{{ $filial['pessoa'] }}</a>
                </td>
                <td class="text-right"><span class="text-muted">{{ formataNumero($filial['valormetafilial']) }}</span></td>
                <td class="text-right"><span class="text-muted">{{ formataNumero($filial['valormetavendedor']) }}</span></td>
                <td class="text-right"><strong>{{ formataNumero($filial['valorvendas']) }}</strong></td>
                <td class="text-right">
                    <span class="text-danger">{{ formataNumero($filial['falta']) }}</span>
                    @if($filial['comissao'])
                        <span class="label label-success">Atingida</span>
                    @endif                                
                </td>
                <td class="text-right">{{ formataNumero($filial['comissao']) }}</td>
            </tr>
            @endforeach
        </tbody> 
    </table>
</div>
<div class="clearfix"></div>
<h3>Vendedores</h3>
<div class="panel panel-default">            
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Filial</th>
                <th>Vendedor</th>
                <th class="text-right">Meta</th>
                <th class="text-right">Vendas</th>
                <th class="text-right">Falta</th>
                <th class="text-right">Comissão</th>
                <th class="text-right">Prêmio</th>
                <th class="text-right">Primeiro</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($vendedores as $vendedor)
            <tr>
                <td scope="row">{{ $vendedor['filial'] }}</td>
                <td>
                    <a href="{{ url('pessoa/'.$vendedor['codpessoa']) }}">{{ $vendedor['pessoa'] }}</a>
                    <span class="label label-success pull-right">{{ $i++ }}º</span>
                </td>
                <td class="text-right"><span class="text-muted">{{ formataNumero($vendedor['valormetavendedor']) }}</span></td>
                <td class="text-right"><strong>{{ formataNumero($vendedor['valorvendas']) }}</strong></td>
                <td class="text-right">
                    <span class="text-danger">{{ formataNumero($vendedor['falta']) }}</span>
                    @if($vendedor['metaatingida'])
                        <span class="label label-success">Atingida</span>
                    @endif                                
                </td>
                <td class="text-right">{{ formataNumero($vendedor['valorcomissaovendedor']) }}</td>
                <td class="text-right">{{ formataNumero($vendedor['valorcomissaometavendedor']) }}</td>
                <td class="text-right">{{ formataNumero($vendedor['primeirovendedor']) }}</td>
                <td class="text-right"><strong>{{ formataNumero($vendedor['valortotalcomissao']) }}</strong></td>
            </tr>
            @endforeach
        </tbody> 
    </table>
</div>
<div class="col-sm-6">
    <div class="row">
        <h3>Xerox</h3>
        <div class="panel panel-default">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Filial</th>
                        <th>Vendedor</th>
                        <th class="text-right">Vendas</th>
                        <th class="text-right">Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($xeroxs as $xerox)
                    <tr>
                        <td>{{ $xerox['filial'] }}</td>
                        <td><a href="{{ url('pessoa/'.$xerox['codpessoa']) }}">{{ $xerox['pessoa'] }}</a></td>
                        <td class="text-right"><strong>{{ formataNumero($xerox['valorvendas']) }}</strong></td>
                        <td class="text-right"><strong>{{ formataNumero($xerox['comissao']) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody> 
            </table> 
        </div>
    </div>
</div>
<div class="col-sm-6"></div>
<div class="col-sm-8">
    <h3>Gráfico</h3>
    <div id="piechart{{ $filial['codfilial'] }}"></div>
    
</div>
<div class="col-sm-12"><div id="vendas{{ $filial['codfilial'] }}"></div></div>
<?php
    $periodoinicial = $model->periodoinicial->subDay();
    $periodofinal = ($model->periodofinal <= Carbon\Carbon::today() ? $model->periodofinal : Carbon\Carbon::today());
    $periodofinal->startOfDay();
    
    $dias = [];
    while ($periodoinicial->lte($periodofinal)) {
        $dia = substr($periodoinicial->addDay()->copy()->toW3cString(), 0, -6);
        $dias[$dia] = [$dia];
    }  
    
    foreach($vendedores as $vendedor) {
        foreach($vendedor['valorvendaspordata'] as $vendas) {
            array_push($dias[$vendas->data], $vendas->valorvendas);
        }

        $valorvendaspordata = collect($vendedor['valorvendaspordata']);
        foreach ($dias as $dia) {
           if(!$valorvendaspordata->contains('data', $dia[0])){
               array_push($dias[$dia[0]], 0);
           }
        }
    }
?>
<script type="text/javascript">
    google.charts.load('current', {
        'packages':['corechart', 'line'],
        'language': 'pt_BR',
    });

    google.charts.setOnLoadCallback(drawChart);
    google.charts.setOnLoadCallback(drawChartLine);
    
    function drawChart() {
        DataTableFilial[{{ $filial['codfilial'] }}] = [
            ['Vendedores', 'Vendas'],
            @foreach($vendedores as $vendedor)
            ["{{ $vendedor['pessoa'] }}", {{ $vendedor['valorvendas'] }}],
            @endforeach
            ['Xerox', {{ $xerox['valorvendas'] }}],
            ['Sem Vendedor', {{ $filial['valorvendas'] - array_sum(array_column($vendedores->toArray(), 'valorvendas')) -  $xerox['valorvendas'] }}]
        ];

        var data = google.visualization.arrayToDataTable(DataTableFilial[{{ $filial['codfilial'] }}]);
        optionsFilial[{{ $filial['codfilial'] }}] = {
            title: 'Divisão',
            'width':900,
            'height':500,
        };

        piechartFilial[{{ $filial['codfilial'] }}] = new google.visualization.PieChart(document.getElementById('piechart'+{{ $filial['codfilial'] }}));
        piechartFilial[{{ $filial['codfilial'] }}].draw(data, optionsFilial[{{ $filial['codfilial'] }}]);
    }

    function drawChartLine() {
        DataTableVendas[{{ $filial['codfilial'] }}] = new google.visualization.DataTable();
        DataTableVendas[{{ $filial['codfilial'] }}].addColumn('date', 'Dia');
        @foreach($vendedores as $vendedor)
        DataTableVendas[{{ $filial['codfilial'] }}].addColumn('number', "{{ $vendedor['pessoa'] }}");
        @endforeach

        DataTableVendas[{{ $filial['codfilial'] }}].addRows([
        @foreach(array_values($dias) as $dia)
        <?php $data = $dia[0]; array_shift($dia);?>
        @if(array_sum($dia) > 0)
        [new Date("{{ $data }}"), {{ implode(',', $dia) }}],
        @endif
        @endforeach
        ]);

        optionsVendas[{{ $filial['codfilial'] }}] = {
            title: 'Vendas por dia',
            'width': 1000,
            'height': 500
        };

        vendasPorDia[{{ $filial['codfilial'] }}] = new google.visualization.LineChart(document.getElementById('vendas'+{{ $filial['codfilial'] }}));
        vendasPorDia[{{ $filial['codfilial'] }}].draw(DataTableVendas[{{ $filial['codfilial'] }}], optionsVendas[{{ $filial['codfilial'] }}]);          

    }  
   
</script>