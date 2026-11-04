@use('App\Services\Billing')
@extends('layouts.app')
@section('title', 'Gastos fijos')
@section('content')
@php($pending = $pendingShare + $pendingRate)
@php($tags = ['ok' => ['state-active', 'Vigente'], 'pending_share' => ['state-warn', 'Falta participación'], 'pending_rate' => ['state-warn', 'Falta cotización'], 'ended' => ['state-paused', 'De baja']])
<div class="page-heading"><div><p class="eyebrow">{{ ucfirst($monthLabel) }}</p><h1>Gastos fijos</h1><p class="muted">Suscripciones y servicios que pagás todos los meses. Lo que está en dólares se pasa a pesos con la cotización guardada para cada mes.</p></div><a class="button button-primary" href="#nuevo-gasto">+ <span>Agregar gasto</span></a></div>
@if ($rateFailed)<div class="flash warn" role="status">No se pudo obtener la cotización de Arq. Los gastos en dólares quedan sin pasar a pesos hasta que la actualices o la cargues a mano.</div>@endif
<section class="metric-grid metric-grid-three">
    <article class="metric-card metric-main"><div class="metric-label">Tu parte del mes</div><div class="metric-value">{{ $pending > 0 && $rows->where('status', 'ok')->isEmpty() ? 'Pendiente' : money($totalArs) }}</div><div class="metric-foot">{{ $pending > 0 ? 'Sin contar '.$pending.' gasto(s) pendiente(s)' : 'Todos los gastos vigentes, en pesos' }}</div></article>
    <article class="metric-card"><div class="metric-label">Dólar del mes</div><div class="metric-value {{ $rate ? '' : 'text-warn' }}">{{ $rate ? money($rate->rate) : 'Sin cotización' }}</div><div class="metric-foot">{{ $rate ? $rate->sourceLabel().($rate->quoted_at ? ' · '.$rate->quoted_at->format('d/m H:i') : '') : ($needsRate ? 'Necesaria para los gastos en dólares' : 'No hay gastos en dólares este mes') }}</div></article>
    <article class="metric-card"><div class="metric-label">Tu parte en dólares</div><div class="metric-value">{{ usd($totalUsd) }}</div><div class="metric-foot">{{ $activeCount }} gasto(s) vigente(s) en el mes</div></article>
</section>
<section class="panel"><div class="panel-heading"><div><h2>Cotización de {{ $monthLabel }}</h2><p>{{ $isCurrent ? 'Se toma el precio de compra de USDC en Arq la primera vez que abrís el mes y queda guardado hasta que lo actualices.' : 'Mes cerrado: conserva la cotización que tenía. Solo cambia si la cargás a mano.' }}</p></div></div>
    <div class="stacked-details panel-body">
        @if ($isCurrent)
            <form method="post" action="{{ route('fixed-expenses.rate.refresh') }}" @if ($rate) data-confirm="¿Reemplazar la cotización de este mes por el precio actual de Arq? Cambia el total en pesos del mes." @endif>@csrf<button class="button button-small" type="submit">{{ $rate ? 'Actualizar con Arq' : 'Tomar cotización de Arq' }}</button></form>
        @endif
        <details class="quick-payment"><summary>Cargar cotización a mano</summary>
            <form method="post" action="{{ route('fixed-expenses.rate.store') }}">@csrf<input type="hidden" name="month" value="{{ $month }}">
                <div class="form-grid"><label class="field"><span>Pesos por dólar en {{ $monthLabel }}</span><input type="number" name="rate" min="1" step="0.0001" value="{{ $rate?->rate }}" placeholder="Ej. 1668.81" required></label></div>
                <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar cotización</button></div>
            </form>
        </details>
    </div>
</section>
<section class="panel client-panel"><div class="panel-heading"><div><h2>Gastos de {{ $monthLabel }}</h2><p>Tu parte es el importe completo, o tu participación si el gasto es de un recurso compartido. Lo pendiente no suma al total.</p></div><nav class="heading-tools" aria-label="Cambiar de mes"><a class="text-link" href="{{ route('fixed-expenses.index', ['mes' => $previousMonth]) }}">← Mes anterior</a>@if ($nextMonth)<a class="text-link" href="{{ route('fixed-expenses.index', ['mes' => $nextMonth]) }}">Mes siguiente →</a>@endif</nav></div>
    @forelse ($rows as $row)
        @php($expense = $row['expense'])
        @php($status = $row['status'])
        @php($first = $expense->amounts->last())
        @php($tag = $tags[$status] ?? ['state-paused', $first ? 'Desde '.Billing::monthLabel($first->effective_from) : 'Sin importe'])
        @php($counted = in_array($status, ['ok', 'pending_share', 'pending_rate'], true))
        <article class="client-card" id="gasto-{{ $expense->id }}">
            <div class="client-top"><div class="client-title"><h3>{{ $expense->name }}</h3><p>{{ $expense->categoryLabel() }}@if ($expense->resource) <span>·</span> {{ $expense->resource->name }}@endif</p></div><span class="state-tag {{ $tag[0] }}">{{ $tag[1] }}</span></div>
            <div class="client-numbers">
                <div><small>Importe del mes</small><b>{{ $counted ? $expense->format($row['amount']->amount) : '—' }}</b></div>
                <div><small>Tu parte</small><b class="{{ $status === 'pending_share' ? 'text-warn' : '' }}">@if (! $counted) — @elseif ($status === 'pending_share') Pendiente @else {{ $expense->format($row['mine']) }} ({{ rtrim(rtrim(number_format((float) $row['share'], 2, ',', '.'), '0'), ',') }} %) @endif</b></div>
                <div><small>En pesos</small><b class="{{ in_array($status, ['pending_share', 'pending_rate'], true) ? 'text-warn' : '' }}">@if ($status === 'ok') {{ money($row['ars']) }} @elseif ($status === 'pending_rate') Sin cotización @elseif ($status === 'pending_share') Pendiente @else — @endif</b></div>
            </div>
            @if ($status === 'pending_share')<p class="client-debt text-warn">Cargá tu participación en “{{ $expense->resource->name }}” desde <a class="text-link" href="{{ route('infrastructure.index') }}#recurso-{{ $expense->resource->id }}">Infraestructura</a>. Hasta entonces no se asume ningún reparto.</p>@endif
            @if ($expense->notes)<p class="field-hint">{{ $expense->notes }}</p>@endif
            <div class="stacked-details">
                <details class="quick-payment"><summary>Historial de importe ({{ $expense->amounts->count() }})</summary>
                    <div class="sub-list">
                        @forelse ($expense->amounts as $item)
                            <div class="sub-row"><div><b>{{ (float) $item->amount === 0.0 ? 'De baja' : $expense->format($item->amount) }} desde {{ Billing::monthLabel($item->effective_from) }}</b></div><form method="post" action="{{ route('fixed-expenses.amounts.destroy', [$expense, $item]) }}" data-confirm="¿Eliminar este importe? Los meses que cubría quedan con el anterior.">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar</button></form></div>
                        @empty <div class="field-hint">Sin importes cargados.</div> @endforelse
                    </div>
                    <form method="post" action="{{ route('fixed-expenses.amounts.store', $expense) }}">@csrf
                        <div class="form-grid">
                            <label class="field"><span>Vigente desde (mes)</span><input type="month" name="effective_from" value="{{ $month }}" placeholder="AAAA-MM" required></label>
                            <label class="field"><span>Nuevo importe ({{ $expense->currency }})</span><input type="number" name="amount" min="0" step="0.01" placeholder="Ej. 20" required><small class="field-hint">Poné 0 si lo dejaste de pagar.</small></label>
                        </div>
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Agregar importe</button></div>
                    </form>
                </details>
                <details class="quick-payment"><summary>Editar gasto</summary>
                    <form method="post" action="{{ route('fixed-expenses.update', $expense) }}">@csrf @method('PUT')
                        @include('fixed-expenses.fields', ['item' => $expense])
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar cambios</button></div>
                    </form>
                </details>
            </div>
            <div class="client-actions"><form method="post" action="{{ route('fixed-expenses.destroy', $expense) }}" data-confirm="¿Eliminar este gasto y todo su historial? Si lo dejaste de pagar, mejor cargá un importe 0 desde ese mes.">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar gasto</button></form></div>
        </article>
    @empty <div class="empty-state">Todavía no hay gastos fijos. Empezá por tus suscripciones y las facturas de servidores y bases.</div> @endforelse
</section>
<section class="panel" id="nuevo-gasto">
    <div class="panel-heading"><div><h2>Agregar gasto fijo</h2><p>Si cambia de precio más adelante, agregá un importe nuevo desde su historial: los meses anteriores conservan el que tenían.</p></div></div>
    <form method="post" action="{{ route('fixed-expenses.store') }}">@csrf
        @include('fixed-expenses.fields', ['item' => null])
        <div class="form-grid">
            <label class="field"><span>Importe mensual</span><input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="Ej. 20" required></label>
            <label class="field"><span>Desde (mes)</span><input type="month" name="effective_from" value="{{ old('effective_from', $month) }}" placeholder="AAAA-MM" required></label>
        </div>
        <div class="modal-actions"><button class="button button-primary" type="submit">Guardar gasto</button></div>
    </form>
</section>
@endsection
