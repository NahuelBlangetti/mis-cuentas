@extends('layouts.app')
@section('title', 'Movimientos')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Ingresos y gastos</p><h1>Movimientos</h1><p class="muted">Un registro separado para el negocio y tus finanzas personales.</p></div></div>
<div class="two-columns movement-columns">
    <section class="panel" id="nuevo"><div class="panel-heading"><div><h2>Nuevo movimiento</h2><p>Ingresá los datos del cobro o gasto.</p></div></div>
        <form method="post" action="{{ route('movements.store') }}" class="form-grid movement-form">@csrf
            <label class="field"><span>Tipo</span><select name="type" required><option value="expense">Gasto</option><option value="income">Ingreso</option></select></label>
            <label class="field"><span>Área</span><select name="area" required><option value="business">Negocio</option><option value="personal">Personal</option></select></label>
            <label class="field field-wide"><span>Descripción</span><input name="description" maxlength="180" placeholder="Ej. internet, supermercado, venta" required></label>
            <label class="field"><span>Importe ($)</span><input name="amount" type="number" min="0.01" step="0.01" placeholder="0" required></label>
            <label class="field"><span>Fecha</span><input name="date" type="date" value="{{ now()->toDateString() }}" required></label>
            <label class="field field-wide"><span>Categoría (opcional)</span><input name="category" maxlength="100" placeholder="Ej. servicios, comida, herramientas"></label>
            <div class="field-wide form-submit"><button class="button button-primary" type="submit">Guardar movimiento</button></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h2>Últimos movimientos</h2><p>Se muestran hasta 150 registros recientes.</p></div><div class="heading-tools"><a class="text-link" href="{{ route('movements.export') }}">Exportar CSV</a><form method="get" action="{{ route('movements.index') }}"><select class="compact-select" name="area" onchange="this.form.submit()"><option value="all" {{ $filter === 'all' ? 'selected' : '' }}>Todos</option><option value="business" {{ $filter === 'business' ? 'selected' : '' }}>Negocio</option><option value="personal" {{ $filter === 'personal' ? 'selected' : '' }}>Personal</option></select></form></div></div>
        <div class="movement-list">
            @forelse ($transactions as $movement)
                <article class="movement-item"><span class="activity-icon {{ $movement->type === 'expense' ? 'is-expense' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}</span><div class="movement-detail"><b>{{ $movement->description }}</b><small>{{ $movement->date->format('d/m/Y') }} · {{ $movement->area === 'personal' ? 'Personal' : 'Negocio' }}{{ $movement->category ? ' · '.$movement->category : '' }}</small></div><b class="activity-amount {{ $movement->type === 'expense' ? 'negative' : '' }}">{{ $movement->type === 'expense' ? '−' : '+' }}${{ number_format((float) $movement->amount, 0, ',', '.') }}</b><form method="post" action="{{ route('movements.destroy', $movement) }}" onsubmit="return confirm('¿Eliminar este movimiento?')">@csrf @method('DELETE')<button class="icon-button delete-button" type="submit" aria-label="Eliminar movimiento">×</button></form></article>
            @empty <div class="empty-state">Todavía no hay movimientos registrados.</div> @endforelse
        </div>
    </section>
</div>
@endsection
