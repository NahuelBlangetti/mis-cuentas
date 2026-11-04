@use('App\Models\FixedExpense')
@use('App\Models\PersonalExpense')
@use('App\Services\Billing')
@extends('layouts.app')
@section('title', 'Finanzas personales')
@section('content')
@php($tags = ['ok' => ['state-active', 'Vigente'], 'pending_rate' => ['state-warn', 'Falta cotización'], 'ended' => ['state-paused', 'De baja']])
{{-- Los datos que no pasaron la validación vuelven solo al formulario que los envió: el historial de importes también usa importe y mes. --}}
@php($oldExpense = old('form') === 'expense')
<div class="page-heading"><div><p class="eyebrow">{{ ucfirst($monthLabel) }}</p><h1>Finanzas personales</h1><p class="muted">Tus gastos personales, aparte del negocio. Los recurrentes se suman solos cada mes; el resto lo cargás cuando lo gastás.</p></div><a class="button button-primary" href="#nuevo-gasto">+ <span>Cargar gasto</span></a></div>
@if ($rateFailed)<div class="flash warn" role="status">No se pudo obtener la cotización de Arq. Los gastos en dólares no suman hasta que la actualices o la cargues a mano.</div>@endif
<section class="metric-grid metric-grid-three">
    <article class="metric-card metric-main"><div class="metric-label">Gastos personales del mes</div><div class="metric-value">{{ money($total) }}</div><div class="metric-foot">{{ $pendingRate > 0 ? 'Sin contar '.$pendingRate.' recurrente(s) en dólares sin cotización' : 'Recurrentes más los gastos cargados' }}</div></article>
    <article class="metric-card"><div class="metric-label">Recurrentes</div><div class="metric-value">{{ money($recurringTotal) }}</div><div class="metric-foot">{{ $activeRecurring }} vigente(s) en el mes</div></article>
    <article class="metric-card"><div class="metric-label">Gastos cargados</div><div class="metric-value">{{ money($monthTotal) }}</div><div class="metric-foot">{{ $expenses->count() }} {{ $expenses->count() === 1 ? 'gasto' : 'gastos' }} en el mes</div></article>
</section>
<div class="two-columns">
    <section class="panel"><div class="panel-heading"><div><h2>Gastos de {{ $monthLabel }}</h2><p>Lo que gastaste en el mes, además de los recurrentes.</p></div><nav class="heading-tools" aria-label="Cambiar de mes"><a class="text-link" href="{{ route('personal.index', ['mes' => $previousMonth]) }}">← Mes anterior</a>@if ($nextMonth)<a class="text-link" href="{{ route('personal.index', ['mes' => $nextMonth]) }}">Mes siguiente →</a>@endif</nav></div>
        <div class="movement-list">
            @forelse ($expenses as $expense)
                <article class="movement-item"><span class="activity-icon is-expense">−</span><div class="movement-detail"><b>{{ $expense->description }}</b><small>{{ $expense->date->format('d/m/Y') }} · {{ $expense->categoryLabel() }}</small></div><b class="activity-amount negative">{{ money((float) $expense->amount) }}</b><a class="icon-button edit-button" href="{{ route('personal.index', ['mes' => $month, 'editar' => $expense->id]) }}#nuevo-gasto" aria-label="Editar gasto">✎</a><form method="post" action="{{ route('personal.expenses.destroy', $expense) }}" data-confirm="¿Eliminar este gasto?">@csrf @method('DELETE')<button class="icon-button delete-button" type="submit" aria-label="Eliminar gasto">×</button></form></article>
            @empty <div class="empty-state">No hay gastos cargados en {{ $monthLabel }}.</div> @endforelse
        </div>
    </section>
    {{-- Un gasto ya cargado no cambia de tipo: los recurrentes se editan desde su tarjeta. --}}
    @php($kind = $editing ? 'once' : ($oldExpense ? old('kind', 'once') : $kind))
    <section class="panel" id="nuevo-gasto"><div class="panel-heading"><div><h2>{{ $editing ? 'Editar gasto' : 'Cargar gasto' }}</h2><p>{{ $editing ? 'Cambiá los datos y guardá.' : 'Elegí si lo pagás una sola vez o todos los meses.' }}</p></div></div>
        <form method="post" action="{{ $editing ? route('personal.expenses.update', $editing) : route('personal.expenses.store') }}" class="form-grid movement-form" data-expense-form>@csrf @if ($editing) @method('PUT') @endif<input type="hidden" name="form" value="expense">
            @unless ($editing)
                <fieldset class="kind-choice field-wide"><legend>¿Cada cuánto lo pagás?</legend>
                    <label class="kind-option"><input type="radio" name="kind" value="once" @checked($kind === 'once') data-expense-kind><span><b>Una sola vez</b><small>Súper, nafta, una salida</small></span></label>
                    <label class="kind-option"><input type="radio" name="kind" value="monthly" @checked($kind === 'monthly') data-expense-kind><span><b>Todos los meses</b><small>Alquiler, servicios, suscripciones</small></span></label>
                </fieldset>
            @endunless
            <label class="field field-wide"><span>Descripción</span><input name="description" maxlength="180" value="{{ $oldExpense ? old('description') : $editing?->description }}" placeholder="Ej. súper, alquiler, Netflix" required></label>
            <label class="field"><span>Importe</span><input name="amount" type="number" min="0.01" step="0.01" value="{{ $oldExpense ? old('amount') : $editing?->amount }}" placeholder="0" required></label>
            <label class="field" data-kind="once" @if ($kind !== 'once') hidden @endif><span>Fecha</span><input name="date" type="date" value="{{ $oldExpense ? old('date', $defaultDate) : ($editing?->date->toDateString() ?? $defaultDate) }}" @disabled($kind !== 'once') required></label>
            <label class="field" data-kind="monthly" @if ($kind !== 'monthly') hidden @endif><span>Moneda</span><select name="currency" @disabled($kind !== 'monthly') required>@foreach (FixedExpense::CURRENCIES as $value => $label)<option value="{{ $value }}" @selected(($oldExpense ? old('currency', 'ARS') : 'ARS') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field" data-kind="monthly" @if ($kind !== 'monthly') hidden @endif><span>Desde (mes)</span><input name="effective_from" type="month" value="{{ $oldExpense ? old('effective_from', $month) : $month }}" placeholder="AAAA-MM" @disabled($kind !== 'monthly') required><small class="field-hint">Se suma solo cada mes. Si cambia el precio, cargalo desde su historial.</small></label>
            <label class="field field-wide"><span>Categoría</span><select name="category" required><option value="">Elegí una categoría</option>@foreach (PersonalExpense::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($oldExpense ? old('category') : $editing?->category) === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="field-wide form-submit">@if ($editing)<a class="button button-quiet" href="{{ route('personal.index', ['mes' => $month]) }}">Cancelar edición</a>@endif<button class="button button-primary" type="submit" data-expense-submit>{{ $editing ? 'Guardar cambios' : ($kind === 'monthly' ? 'Guardar gasto recurrente' : 'Guardar gasto') }}</button></div>
        </form>
    </section>
</div>
@include('personal.goal', ['goal' => $goal])
@if ($categories->isNotEmpty())
    <section class="panel"><div class="panel-heading"><div><h2>Por categoría</h2><p>Recurrentes y gastos cargados en {{ $monthLabel }}, en pesos.</p></div></div>
        <div class="summary-list">
            @foreach ($categories as $category)<div class="summary-row"><span>{{ $category['label'] }} <small>· {{ $category['percent'] }} %</small></span><b>{{ money($category['amount']) }}</b></div>@endforeach
            <div class="summary-row summary-total"><span>Total del mes</span><b>{{ money($total) }}</b></div>
        </div>
    </section>
@endif
<section class="panel client-panel" id="recurrentes"><div class="panel-heading"><div><h2>Gastos recurrentes</h2><p>Alquiler, servicios, suscripciones: lo que pagás todos los meses. Cada uno suma desde su mes de inicio hasta que lo des de baja.</p></div><a class="text-link" href="{{ route('personal.index', ['mes' => $month, 'tipo' => 'recurrente']) }}#nuevo-gasto">+ Agregar recurrente</a></div>
    @if ($needsRate)
        <div class="stacked-details panel-body">
            <p class="field-hint">Dólar de {{ $monthLabel }}: <b class="{{ $rate ? '' : 'text-warn' }}">{{ $rate ? money($rate->rate).' · '.$rate->sourceLabel() : 'sin cotización' }}</b>. Es la misma cotización que usan los Gastos fijos.</p>
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
    @endif
    @forelse ($recurring as $row)
        @php($bill = $row['expense'])
        @php($status = $row['status'])
        @php($first = $bill->amounts->last())
        @php($tag = $tags[$status] ?? ['state-paused', $first ? 'Desde '.Billing::monthLabel($first->effective_from) : 'Sin importe'])
        @php($counted = in_array($status, ['ok', 'pending_rate'], true))
        <article class="client-card" id="recurrente-{{ $bill->id }}">
            <div class="client-top"><div class="client-title"><h3>{{ $bill->name }}</h3><p>{{ $bill->categoryLabel() }}</p></div><span class="state-tag {{ $tag[0] }}">{{ $tag[1] }}</span></div>
            <div class="client-numbers">
                <div><small>Importe del mes</small><b>{{ $counted ? $bill->format($row['amount']->amount) : '—' }}</b></div>
                <div><small>En pesos</small><b class="{{ $status === 'pending_rate' ? 'text-warn' : '' }}">@if ($status === 'ok') {{ money($row['ars']) }} @elseif ($status === 'pending_rate') Sin cotización @else — @endif</b></div>
                <div><small>Moneda</small><b>{{ $bill->currency }}</b></div>
            </div>
            @if ($bill->notes)<p class="field-hint">{{ $bill->notes }}</p>@endif
            <div class="stacked-details">
                <details class="quick-payment"><summary>Historial de importe ({{ $bill->amounts->count() }})</summary>
                    <div class="sub-list">
                        @forelse ($bill->amounts as $item)
                            <div class="sub-row"><div><b>{{ (float) $item->amount === 0.0 ? 'De baja' : $bill->format($item->amount) }} desde {{ Billing::monthLabel($item->effective_from) }}</b></div><form method="post" action="{{ route('personal.recurring.amounts.destroy', [$bill, $item]) }}" data-confirm="¿Eliminar este importe? Los meses que cubría quedan con el anterior.">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar</button></form></div>
                        @empty <div class="field-hint">Sin importes cargados.</div> @endforelse
                    </div>
                    <form method="post" action="{{ route('personal.recurring.amounts.store', $bill) }}">@csrf
                        <div class="form-grid">
                            <label class="field"><span>Vigente desde (mes)</span><input type="month" name="effective_from" value="{{ $month }}" placeholder="AAAA-MM" required></label>
                            <label class="field"><span>Nuevo importe ({{ $bill->currency }})</span><input type="number" name="amount" min="0" step="0.01" placeholder="Ej. 25000" required><small class="field-hint">Si cambió el precio, cargá el nuevo. Poné 0 si lo dejaste de pagar.</small></label>
                        </div>
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Agregar importe</button></div>
                    </form>
                </details>
                <details class="quick-payment"><summary>Editar gasto</summary>
                    <form method="post" action="{{ route('personal.recurring.update', $bill) }}">@csrf @method('PUT')
                        @include('personal.recurring-fields', ['item' => $bill])
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar cambios</button></div>
                    </form>
                </details>
            </div>
            <div class="client-actions"><form method="post" action="{{ route('personal.recurring.destroy', $bill) }}" data-confirm="¿Eliminar este gasto y todo su historial? Si lo dejaste de pagar, mejor cargá un importe 0 desde ese mes.">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar gasto</button></form></div>
        </article>
    @empty <div class="empty-state">Todavía no hay gastos recurrentes. <a class="text-link" href="{{ route('personal.index', ['mes' => $month, 'tipo' => 'recurrente']) }}#nuevo-gasto">Cargá el alquiler, los servicios o tus suscripciones →</a></div> @endforelse
</section>
<p class="privacy-note">Por ahora acá solo se guardan gastos. Lo que cobrás a tus clientes sale de Clientes y cobros y de Movimientos.</p>
@endsection
