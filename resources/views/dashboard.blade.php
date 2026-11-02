@extends('layouts.app')
@section('title', 'Resumen')
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ ucfirst($monthLabel) }}</p><h1>Resumen</h1><p class="muted">Tus cobros y movimientos en una sola vista.</p></div><a class="button button-primary" href="{{ route('movements.index') }}#nuevo">+ <span>Registrar movimiento</span></a></div>
<section class="metric-grid">
    <article class="metric-card metric-main"><div class="metric-label">Abonos del mes</div><div class="metric-value">{{ money($expected) }}</div><div class="metric-foot">{{ $clients->where('active', true)->count() }} clientes activos · {{ $clients->where('active', true)->sum('systems') }} sistemas</div></article>
    <article class="metric-card"><div class="metric-label">Cobrado a clientes</div><div class="metric-value">{{ money($collected) }}</div><div class="metric-foot">Pagos imputados a este mes</div></article>
    <article class="metric-card"><div class="metric-label">Pendiente de cobro</div><div class="metric-value">{{ money($pending) }}</div><div class="metric-foot">{{ $arrears > 0 ? 'Incluye '.money($arrears).' de meses anteriores' : 'Según los abonos generados' }}</div></article>
    <article class="metric-card"><div class="metric-label">Gastos del negocio</div><div class="metric-value">{{ money($businessExpenses) }}</div><div class="metric-foot">Registrados en el mes</div></article>
</section>
<div class="two-columns">
    <section class="panel"><div class="panel-heading"><div><h2>Cobros del mes</h2><p>Seguimiento por cliente</p></div><a class="text-link" href="{{ route('clients.index') }}">Ver clientes →</a></div>
        @forelse ($clients as $client)
            @php($balance = $balances[$client->id])
            <div class="billing-row"><span class="status-dot {{ $balance['total'] > 0 ? 'waiting' : '' }}"></span><div class="billing-client"><b>{{ $client->name }}</b><small>{{ $client->active ? $client->systems.' sistema(s) · vence el '.$client->due_day : 'Pausado' }}</small></div><div class="billing-sum"><b>{{ money($balance['paid']) }} <small>/ {{ money($balance['charged']) }}</small></b><span class="{{ $balance['total'] > 0 ? 'text-warn' : 'text-ok' }}">{{ $balance['total'] > 0 ? 'Faltan '.money($balance['total']) : 'Al día' }}</span>@if ($balance['arrears'] > 0)<small class="arrears text-warn">{{ money($balance['arrears']) }} de meses anteriores</small>@endif</div>@if ($client->phone && $balance['total'] > 0)<a class="icon-button whatsapp-button" href="{{ \App\Services\WhatsApp::link($client->phone, \App\Services\WhatsApp::chargeMessage($client, $balance['months'])) }}" target="_blank" rel="noopener noreferrer" aria-label="Enviar cobro por WhatsApp a {{ $client->name }}" title="Enviar cobro por WhatsApp">✉</a>@endif</div>
        @empty <div class="empty-state">Todavía no hay clientes. Agregá el primero desde Clientes y cobros.</div> @endforelse
    </section>
    <section class="panel"><div class="panel-heading"><div><h2>Actividad reciente</h2><p>Últimos movimientos registrados</p></div><a class="text-link" href="{{ route('movements.index') }}">Ver todo →</a></div>
        @forelse ($recent as $movement)
            <div class="activity-row"><span class="activity-icon {{ $movement->type === 'expense' ? 'is-expense' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}</span><div class="activity-info"><b>{{ $movement->description }}</b><small>{{ $movement->date->format('d/m/Y') }} · {{ $movement->area === 'personal' ? 'Personal' : 'Negocio' }}{{ ! $movement->billing_month && $movement->clientLabel() ? ' · '.$movement->clientLabel() : '' }}</small></div><b class="activity-amount {{ $movement->type === 'expense' ? 'negative' : '' }}">{{ money((float) $movement->amount) }}</b></div>
        @empty <div class="empty-state">Tus cobros y gastos aparecerán acá.</div> @endforelse
    </section>
</div>
<p class="privacy-note">Registro personal de ingresos y gastos. No se conecta a tus bancos ni reemplaza la contabilidad formal.</p>
@endsection
