@extends('layouts.app')
@section('title', 'Movimientos')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Negocio</p><h1>Movimientos</h1><p class="muted">Ingresos y gastos del negocio. Tus gastos personales van aparte, en <a class="text-link" href="{{ route('personal.index') }}">Finanzas personales</a>.</p></div></div>
<div class="two-columns movement-columns">
    <section class="panel" id="nuevo"><div class="panel-heading"><div><h2 id="movement-form-title">Nuevo movimiento</h2><p>Ingresá los datos del cobro o gasto.</p></div></div>
        <form method="post" action="{{ route('movements.store') }}" class="form-grid movement-form" id="movement-form" aria-labelledby="movement-form-title">@csrf<input type="hidden" name="_method" value="POST">
            <label class="field"><span>Tipo</span><select name="type" required><option value="expense">Gasto</option><option value="income">Ingreso</option></select></label>
            <label class="field field-wide" data-client-field><span>Cliente (opcional)</span><select name="client"><option value="">Sin cliente</option>@if ($clients->isNotEmpty())<optgroup label="Clientes registrados">@foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->label() }}{{ $client->active ? '' : ' (pausado)' }}</option>@endforeach</optgroup>@endif<option value="occasional">Cliente ocasional (pago único)</option></select><small class="field-hint">Un trabajo extra no salda el abono mensual: los abonos se cobran desde Clientes y cobros.</small></label>
            <label class="field field-wide" data-client-name-field hidden><span>Nombre del cliente ocasional</span><input name="client_name" maxlength="160" placeholder="Ej. Juan Pérez, Kiosco Centro" disabled></label>
            <label class="field field-wide"><span>Descripción</span><input name="description" maxlength="180" placeholder="Ej. dominio, instalación, venta" required></label>
            <label class="field"><span>Importe ($)</span><input name="amount" type="number" min="0.01" step="0.01" placeholder="0" required></label>
            <label class="field"><span>Fecha</span><input name="date" type="date" value="{{ now()->toDateString() }}" required></label>
            <label class="field field-wide"><span>Categoría (opcional)</span><input name="category" maxlength="100" placeholder="Ej. servicios, herramientas, ventas"></label>
            <div class="field-wide form-submit"><button class="button button-quiet" type="button" data-cancel-movement hidden>Cancelar edición</button><button class="button button-primary" type="submit">Guardar movimiento</button></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h2>Movimientos</h2><p>{{ $transactions->total() }} {{ $transactions->total() === 1 ? 'registro' : 'registros' }}, del más reciente al más antiguo.</p></div><div class="heading-tools"><a class="text-link" href="{{ route('movements.export') }}">Exportar CSV</a>@if ($hasPersonal)<form method="get" action="{{ route('movements.index') }}"><select class="compact-select" name="area" aria-label="Filtrar por área" data-autosubmit><option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Todos</option><option value="business" {{ $filter === 'business' ? 'selected' : '' }}>Negocio</option><option value="personal" {{ $filter === 'personal' ? 'selected' : '' }}>Personales anteriores</option></select></form>@endif</div></div>
        <div class="movement-list">
            @forelse ($transactions as $movement)
                <article class="movement-item"><span class="activity-icon {{ $movement->type === 'expense' ? 'is-expense' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}</span><div class="movement-detail"><b>{{ $movement->description }}</b><small>{{ $movement->date->format('d/m/Y') }}{{ $movement->area === 'personal' ? ' · Personal' : '' }}{{ $movement->category ? ' · '.$movement->category : '' }}{{ $movement->billing_month ? ' · abono de '.\App\Services\Billing::monthLabel($movement->billing_month) : ($movement->clientLabel() ? ' · '.$movement->clientLabel() : '') }}</small></div><b class="activity-amount {{ $movement->type === 'expense' ? 'negative' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}{{ money((float) $movement->amount) }}</b><button class="icon-button edit-button" type="button" aria-label="Editar movimiento" data-edit-movement data-url="{{ route('movements.update', $movement) }}" data-type="{{ $movement->type }}" data-area="{{ $movement->area }}" data-description="{{ $movement->description }}" data-category="{{ $movement->category }}" data-amount="{{ $movement->amount }}" data-date="{{ $movement->date->toDateString() }}" data-client="{{ $movement->clientChoice() }}" data-client_name="{{ $movement->client_name }}" data-payment="{{ $movement->isSubscriptionPayment() ? 1 : 0 }}">✎</button><form method="post" action="{{ route('movements.destroy', $movement) }}" data-confirm="¿Eliminar este movimiento?">@csrf @method('DELETE')<button class="icon-button delete-button" type="submit" aria-label="Eliminar movimiento">×</button></form></article>
            @empty <div class="empty-state">Todavía no hay movimientos registrados.</div> @endforelse
        </div>
        @if ($transactions->hasPages())
            <nav class="pager" aria-label="Páginas de movimientos">@if ($transactions->previousPageUrl())<a class="text-link" href="{{ $transactions->previousPageUrl() }}">← Más recientes</a>@else<span></span>@endif<span>Página {{ $transactions->currentPage() }} de {{ $transactions->lastPage() }}</span>@if ($transactions->nextPageUrl())<a class="text-link" href="{{ $transactions->nextPageUrl() }}">Más antiguos →</a>@else<span></span>@endif</nav>
        @endif
    </section>
</div>
@endsection
