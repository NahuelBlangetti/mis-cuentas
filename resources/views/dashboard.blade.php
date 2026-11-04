@extends('layouts.app')
@section('title', 'Resumen')
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ ucfirst($monthLabel) }}</p><h1>Resumen</h1><p class="muted">Tus cobros y movimientos en una sola vista.</p></div><a class="button button-primary" href="{{ route('movements.index') }}#nuevo">+ <span>Registrar movimiento</span></a></div>
<section class="metric-grid">
    <article class="metric-card metric-main"><div class="metric-label">Abonos del mes</div><div class="metric-value">{{ money($expected) }}</div><div class="metric-foot">{{ $clients->where('active', true)->count() }} clientes activos · {{ $clients->where('active', true)->sum('systems') }} sistemas</div></article>
    <article class="metric-card"><div class="metric-label">Cobrado a clientes</div><div class="metric-value">{{ money($collected) }}</div><div class="metric-foot">Pagos imputados a este mes</div></article>
    <article class="metric-card"><div class="metric-label">Pendiente de cobro</div><div class="metric-value">{{ money($pending) }}</div><div class="metric-foot">{{ $arrears > 0 ? 'Incluye '.money($arrears).' de meses anteriores' : 'Según los abonos generados' }}</div></article>
    <article class="metric-card"><div class="metric-label">Resultado del mes</div><div class="metric-value {{ $summary['result'] < 0 ? 'negative' : '' }}">{{ money($summary['result']) }}</div><div class="metric-foot">{{ $summary['fixedPending'] > 0 ? 'Sin contar '.$summary['fixedPending'].' gasto(s) fijo(s) pendiente(s)' : 'Ingresos menos gastos del negocio' }}</div></article>
</section>
@include('personal.goal', ['goal' => $goal, 'link' => true])
<section class="panel"><div class="panel-heading"><div><h2>Ingresos y gastos del negocio</h2><p>Según la fecha de cada movimiento. Si un gasto ya está en Gastos fijos, no lo registres también como movimiento: se contaría dos veces.</p></div><a class="text-link" href="{{ route('fixed-expenses.index') }}">Ver gastos fijos →</a></div>
    <div class="summary-list">
        <div class="summary-row summary-group"><span>Ingresos</span><b>{{ money($summary['income']) }}</b></div>
        <div class="summary-row"><span>Abonos cobrados en el mes</span><b>{{ money($summary['subscriptionIncome']) }}</b></div>
        <div class="summary-row"><span>Otros ingresos</span><b>{{ money($summary['otherIncome']) }}</b></div>
        <div class="summary-row summary-group"><span>Gastos</span><b>{{ money(-$summary['expenses']) }}</b></div>
        <div class="summary-row"><span>Gastos fijos, tu parte en pesos @if ($summary['fixedPending'] > 0)<small class="text-warn">· {{ $summary['fixedPending'] }} pendiente(s) sin sumar</small>@endif</span><b>{{ money($summary['fixedExpenses']) }}</b></div>
        <div class="summary-row"><span>Gastos registrados en movimientos</span><b>{{ money($summary['recordedExpenses']) }}</b></div>
        <div class="summary-row summary-total"><span>Resultado del mes</span><b class="{{ $summary['result'] < 0 ? 'negative' : '' }}">{{ money($summary['result']) }}</b></div>
    </div>
    @if ($summary['rateFailed'])<p class="summary-note text-warn">No se pudo obtener la cotización de Arq: los gastos en dólares no suman hasta que la tomes o la cargues en Gastos fijos.</p>@endif
</section>
<div class="two-columns">
    <section class="panel"><div class="panel-heading"><div><h2>Cobros del mes</h2><p>Seguimiento por cliente</p></div><a class="text-link" href="{{ route('clients.index') }}">Ver clientes →</a></div>
        @forelse ($clients as $client)
            @php($balance = $balances[$client->id])
            <div class="billing-row"><span class="status-dot {{ $balance['total'] > 0 ? 'waiting' : '' }}"></span><div class="billing-client"><b>{{ $client->label() }}</b><small>{{ $client->active ? $client->systems.' sistema(s) · vence el '.$client->due_day : 'Pausado' }}</small></div><div class="billing-sum"><b>{{ money($balance['paid']) }} <small>/ {{ money($balance['charged']) }}</small></b><span class="{{ $balance['total'] > 0 ? 'text-warn' : 'text-ok' }}">{{ $balance['total'] > 0 ? 'Faltan '.money($balance['total']) : 'Al día' }}</span>@if ($balance['arrears'] > 0)<small class="arrears text-warn">{{ money($balance['arrears']) }} de meses anteriores</small>@endif</div>@if ($client->phone && $balance['total'] > 0)<a class="icon-button whatsapp-button" href="{{ \App\Services\WhatsApp::link($client->phone, \App\Services\WhatsApp::chargeMessage($client, $balance['months'])) }}" target="_blank" rel="noopener noreferrer" aria-label="Enviar cobro por WhatsApp a {{ $client->name }}" title="Enviar cobro por WhatsApp">✉</a>@endif</div>
        @empty <div class="empty-state">Todavía no hay clientes. Agregá el primero desde Clientes y cobros.</div> @endforelse
    </section>
    <section class="panel"><div class="panel-heading"><div><h2>Actividad reciente</h2><p>Últimos movimientos registrados</p></div><a class="text-link" href="{{ route('movements.index') }}">Ver todo →</a></div>
        @forelse ($recent as $movement)
            <div class="activity-row"><span class="activity-icon {{ $movement->type === 'expense' ? 'is-expense' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}</span><div class="activity-info"><b>{{ $movement->description }}</b><small>{{ $movement->date->format('d/m/Y') }}{{ $movement->area === 'personal' ? ' · Personal' : '' }}{{ ! $movement->billing_month && $movement->clientLabel() ? ' · '.$movement->clientLabel() : '' }}</small></div><b class="activity-amount {{ $movement->type === 'expense' ? 'negative' : '' }}">{{ money((float) $movement->amount) }}</b></div>
        @empty <div class="empty-state">Tus cobros y gastos aparecerán acá.</div> @endforelse
    </section>
</div>
<p class="privacy-note">Registro personal de ingresos y gastos. No se conecta a tus bancos ni reemplaza la contabilidad formal.</p>
@endsection
