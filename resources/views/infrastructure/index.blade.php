@use('App\Services\Billing')
@extends('layouts.app')
@section('title', 'Infraestructura')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Costos compartidos</p><h1>Infraestructura</h1><p class="muted">Servidores y clústeres donde corren los sistemas. Cada recurso se registra una sola vez aunque lo usen varios clientes.</p></div><a class="button button-primary" href="#nuevo-recurso">+ <span>Agregar recurso</span></a></div>
<section class="metric-grid metric-grid-three">
    <article class="metric-card metric-main"><div class="metric-label">Recursos activos</div><div class="metric-value">{{ $activeCount }}</div><div class="metric-foot">Servidores, clústeres y otros servicios</div></article>
    <article class="metric-card"><div class="metric-label">Participación pendiente</div><div class="metric-value">{{ $pendingCount }}</div><div class="metric-foot">Recursos activos sin porcentaje para {{ Billing::monthLabel($month) }}</div></article>
    <article class="metric-card"><div class="metric-label">Clientes vinculados</div><div class="metric-value">{{ $clientsCount }}</div><div class="metric-foot">Por sus instalaciones o bases</div></article>
</section>
<section class="panel client-panel"><div class="panel-heading"><div><h2>Recursos registrados</h2><p>Tu participación es el porcentaje del costo que pagás vos. Sin porcentaje cargado no se asume ningún reparto.</p></div></div>
    @forelse ($resources as $resource)
        @php($share = $resource->shareFor($month))
        @php($clients = $resource->clients())
        <article class="client-card" id="recurso-{{ $resource->id }}">
            <div class="client-top"><div class="client-title"><h3>{{ $resource->name }}</h3><p>{{ $resource->providerLabel() }} <span>·</span> {{ $resource->kindLabel() }}@if ($resource->external_id) <span>·</span> ID {{ $resource->external_id }}@endif</p></div><span class="state-tag {{ $resource->active ? 'state-active' : 'state-paused' }}">{{ $resource->active ? 'Activo' : 'Dado de baja' }}</span></div>
            <div class="client-numbers">
                <div><small>Tu participación</small><b class="{{ $share ? '' : 'text-warn' }}">{{ $share ? $share->percentLabel() : 'Pendiente' }}</b></div>
                <div><small>Vigente desde</small><b>{{ $share ? ucfirst(Billing::monthLabel($share->effective_from)) : '—' }}</b></div>
                <div><small>Uso</small><b>{{ $resource->installations->count() }} inst. · {{ $resource->databases->count() }} bases</b></div>
            </div>
            <p class="client-debt muted">{{ $clients->isEmpty() ? 'Todavía no hay instalaciones ni bases vinculadas.' : 'Clientes: '.$clients->map->label()->implode(', ') }}</p>
            @if ($resource->notes)<p class="field-hint">{{ $resource->notes }}</p>@endif
            <div class="stacked-details">
                <details class="quick-payment"><summary>Historial de participación ({{ $resource->shares->count() }})</summary>
                    <div class="sub-list">
                        @forelse ($resource->shares as $item)
                            <div class="sub-row"><div><b>{{ $item->percentLabel() }} desde {{ Billing::monthLabel($item->effective_from) }}</b>@if ($item->notes)<small>{{ $item->notes }}</small>@endif</div><form method="post" action="{{ route('infrastructure.shares.destroy', [$resource, $item]) }}" data-confirm="¿Eliminar esta participación? Los meses que cubría quedan con la anterior o pendientes.">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar</button></form></div>
                        @empty <div class="field-hint">Sin participación cargada.</div> @endforelse
                    </div>
                    <form method="post" action="{{ route('infrastructure.shares.store', $resource) }}">@csrf
                        <div class="form-grid">
                            <label class="field"><span>Vigente desde (mes)</span><input type="month" name="effective_from" value="{{ $month }}" placeholder="AAAA-MM" required></label>
                            <label class="field"><span>Tu participación (%)</span><input type="number" name="share_percent" min="0" max="100" step="0.01" placeholder="Ej. 40" required></label>
                            <label class="field field-wide"><span>Nota (opcional)</span><input name="notes" maxlength="500" placeholder="Ej. acordado con tu socio el 3/11"></label>
                        </div>
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Agregar participación</button></div>
                    </form>
                </details>
                <details class="quick-payment"><summary>Editar recurso</summary>
                    <form method="post" action="{{ route('infrastructure.update', $resource) }}">@csrf @method('PUT')
                        @include('infrastructure.fields', ['item' => $resource])
                        <div class="form-grid"><label class="check-line field-wide"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($resource->active)> Recurso activo</label></div>
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar cambios</button></div>
                    </form>
                </details>
            </div>
            @unless ($resource->installations->isNotEmpty() || $resource->databases->isNotEmpty() || $resource->fixed_expenses_count > 0)
                <div class="client-actions"><form method="post" action="{{ route('infrastructure.destroy', $resource) }}" data-confirm="¿Eliminar este recurso y su historial de participación?">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar recurso</button></form></div>
            @endunless
        </article>
    @empty <div class="empty-state">Todavía no hay recursos. Empezá por el clúster de bases y los servidores donde corren tus sistemas.</div> @endforelse
</section>
<section class="panel" id="nuevo-recurso">
    <div class="panel-heading"><div><h2>Agregar recurso</h2><p>Después vinculalo desde las instalaciones de cada cliente.</p></div></div>
    <form method="post" action="{{ route('infrastructure.store') }}">@csrf
        @include('infrastructure.fields', ['item' => null])
        <div class="modal-actions"><button class="button button-primary" type="submit">Guardar recurso</button></div>
    </form>
</section>
@endsection
