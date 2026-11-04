@php($met = $goal['coverage'] !== null && $goal['balance'] >= 0)
<section class="panel goal-panel" id="objetivo"><div class="panel-heading"><div><h2>Objetivo personal</h2><p>Que lo que cobrás a tus clientes en el mes, abonos y pagos extra, pague tus gastos personales y los del negocio.</p></div>@if ($link ?? false)<a class="text-link" href="{{ route('personal.index') }}#objetivo">Ver gastos personales →</a>@endif</div>
    <div class="goal-body">
        @if ($goal['coverage'] === null)
            <p class="goal-status"><b class="goal-percent">Sin gastos</b><span>Cargá tus gastos personales y fijos para medir el objetivo.</span></p>
        @else
            <p class="goal-status"><b class="goal-percent {{ $met ? 'text-ok' : 'text-warn' }}">{{ $goal['coverage'] }} %</b><span>{{ $met ? 'Cubierto: te sobran '.money($goal['balance']).' de lo cobrado.' : 'Te faltan '.money(-$goal['balance']).' para cubrir todos los gastos.' }}</span></p>
            <progress class="goal-progress {{ $met ? 'is-met' : '' }}" max="100" value="{{ min(100, $goal['coverage']) }}" aria-label="Gastos cubiertos por lo cobrado a clientes">{{ $goal['coverage'] }} %</progress>
        @endif
    </div>
    <div class="summary-list">
        <div class="summary-row summary-group"><span>Cobrado a clientes</span><b>{{ money($goal['income']) }}</b></div>
        <div class="summary-row"><span>Abonos mensuales</span><b>{{ money($goal['subscriptionIncome']) }}</b></div>
        <div class="summary-row"><span>Pagos extra y otros ingresos del negocio</span><b>{{ money($goal['otherIncome']) }}</b></div>
        <div class="summary-row summary-group"><span>Gastos a cubrir</span><b>{{ money(-$goal['expenses']) }}</b></div>
        <div class="summary-row"><span>Gastos personales</span><b>{{ money($goal['personalExpenses']) }}</b></div>
        <div class="summary-row"><span>Gastos del negocio</span><b>{{ money($goal['businessExpenses']) }}</b></div>
        <div class="summary-row summary-total"><span>{{ $goal['balance'] >= 0 ? 'Sobrante del mes' : 'Faltante del mes' }}</span><b class="{{ $goal['balance'] < 0 ? 'negative' : '' }}">{{ money($goal['balance']) }}</b></div>
    </div>
    @if ($goal['pending'] > 0)<p class="summary-note text-warn">Sin contar {{ $goal['pending'] }} gasto(s) pendiente(s) de cotización o de participación.</p>@endif
</section>
