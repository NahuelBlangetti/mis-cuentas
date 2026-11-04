@extends('layouts.app')
@section('title', 'Instalaciones de '.$client->name)
@section('content')
@php
    // Opciones de recurso escapadas; el seleccionado puede ser un recurso dado de baja que sigue vinculado.
    $resourceOptions = function ($selected) use ($resources) {
        $html = '<option value="">Sin asignar</option>';
        foreach ($resources as $resource) {
            $label = $resource->name.' · '.$resource->kindLabel().($resource->active ? '' : ' (dado de baja)');
            $html .= '<option value="'.$resource->id.'"'.((int) $selected === $resource->id ? ' selected' : '').'>'.e($label).'</option>';
        }
        return $html;
    };
@endphp
<div class="page-heading"><div><p class="eyebrow">{{ $client->name }}</p><h1>Instalaciones de producción</h1><p class="muted">Cada sistema, el servidor donde corre y sus bases MySQL/MariaDB con usuario de solo lectura.</p></div><a class="button" href="{{ route('clients.index') }}">Volver a clientes</a></div>
@if ($resources->isEmpty())
    <div class="flash success" role="status">Todavía no registraste servidores ni clústeres. <a class="text-link" href="{{ route('infrastructure.index') }}">Cargalos en Infraestructura</a> para vincularlos y no duplicar el costo de un clúster compartido.</div>
@endif
<section class="panel client-panel">
    <div class="panel-heading"><div><h2>Sistemas registrados</h2><p>Guardar los datos no comprueba la conexión. Las métricas y el ingreso de soporte aún no están habilitados.</p></div><a class="text-link" href="{{ route('infrastructure.index') }}">Infraestructura →</a></div>
    @forelse ($installations as $installation)
        <article class="client-card">
            <div class="client-top"><div class="client-title"><h3>{{ $installation->name }}</h3><p class="url">{{ $installation->url }}</p></div></div>
            <div class="sub-list">
                <div class="sub-row"><div><b>Servidor</b><small>{{ $installation->resource ? $installation->resource->name.' · '.$installation->resource->kindLabel() : 'Sin asignar' }}</small></div></div>
                @forelse ($installation->databases as $database)
                    <div class="sub-row"><div><b>Base {{ $database->name }}</b><small>{{ $database->resource ? $database->resource->name : 'Clúster sin asignar' }} · Credenciales cifradas · Conexión no verificada</small></div><form method="post" action="{{ route('clients.installations.databases.destroy', [$client, $installation, $database]) }}" data-confirm="¿Eliminar esta base y sus credenciales guardadas?">@csrf @method('DELETE')<button class="button button-small button-quiet" type="submit">Eliminar</button></form></div>
                @empty <div class="sub-row"><div><b>Sin bases</b><small>Agregá la base cuando tengas el usuario de solo lectura.</small></div></div> @endforelse
            </div>
            <div class="stacked-details">
                @foreach ($installation->databases as $database)
                    <details class="quick-payment"><summary>Editar base {{ $database->name }}</summary>
                        <form method="post" action="{{ route('clients.installations.databases.update', [$client, $installation, $database]) }}">@csrf @method('PUT')
                            <div class="form-grid">
                                <label class="field"><span>Nombre de la base</span><input name="name" maxlength="160" value="{{ $database->name }}" required></label>
                                <label class="field"><span>Clúster o servidor</span><select name="infrastructure_resource_id">{!! $resourceOptions($database->infrastructure_resource_id) !!}</select></label>
                            </div>
                            <p class="field-hint form-note">Para reemplazar las credenciales completá los cinco datos. Si los dejás vacíos, se conservan las actuales.</p>
                            @include('clients.connection-fields', ['optional' => true])
                            <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar base</button></div>
                        </form>
                    </details>
                @endforeach
                <details class="quick-payment"><summary>+ Agregar base</summary>
                    <form method="post" action="{{ route('clients.installations.databases.store', [$client, $installation]) }}">@csrf
                        <div class="form-grid">
                            <label class="field"><span>Nombre de la base</span><input name="name" maxlength="160" placeholder="Ej. Sucursal centro" required></label>
                            <label class="field"><span>Clúster o servidor</span><select name="infrastructure_resource_id">{!! $resourceOptions(null) !!}</select></label>
                        </div>
                        @include('clients.connection-fields', ['optional' => false])
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar base</button></div>
                    </form>
                </details>
                <details class="quick-payment"><summary>Editar instalación</summary>
                    <form method="post" action="{{ route('clients.installations.update', [$client, $installation]) }}">@csrf @method('PUT')
                        <div class="form-grid">
                            <label class="field"><span>Nombre del sistema o sucursal</span><input name="name" maxlength="160" value="{{ $installation->name }}" required></label>
                            <label class="field"><span>URL del sistema (HTTPS)</span><input name="url" type="url" maxlength="2048" value="{{ $installation->url }}" required></label>
                            <label class="field field-wide"><span>Servidor donde corre</span><select name="infrastructure_resource_id">{!! $resourceOptions($installation->infrastructure_resource_id) !!}</select></label>
                        </div>
                        <div class="modal-actions"><button class="button button-primary button-small" type="submit">Guardar instalación</button></div>
                    </form>
                </details>
            </div>
            <div class="client-actions"><a class="button button-small" href="{{ $installation->url }}" target="_blank" rel="noopener noreferrer">Abrir sistema</a><form method="post" action="{{ route('clients.installations.destroy', [$client, $installation]) }}" data-confirm="¿Eliminar esta instalación, sus bases y credenciales guardadas?">@csrf @method('DELETE')<button class="button button-small" type="submit">Eliminar instalación</button></form></div>
        </article>
    @empty <div class="empty-state">Todavía no hay instalaciones cargadas.</div> @endforelse
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Agregar instalación</h2><p>La base es opcional y se puede agregar después. La contraseña no se muestra ni se recupera.</p></div></div>
    <form method="post" action="{{ route('clients.installations.store', $client) }}">
        @csrf
        <div class="form-grid">
            <label class="field"><span>Nombre del sistema o sucursal</span><input name="name" maxlength="160" value="{{ old('name') }}" required></label>
            <label class="field"><span>URL del sistema (HTTPS)</span><input name="url" type="url" maxlength="2048" placeholder="https://sistema.ejemplo.com" value="{{ old('url') }}" required></label>
            <label class="field"><span>Servidor donde corre</span><select name="infrastructure_resource_id">{!! $resourceOptions(old('infrastructure_resource_id')) !!}</select></label>
            <label class="field"><span>Clúster de la base</span><select name="database_resource_id">{!! $resourceOptions(old('database_resource_id')) !!}</select></label>
        </div>
        @include('clients.connection-fields', ['optional' => true])
        <div class="modal-actions"><button class="button button-primary" type="submit">Guardar instalación</button></div>
    </form>
</section>
@endsection
