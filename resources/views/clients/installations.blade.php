@extends('layouts.app')
@section('title', 'Instalaciones de '.$client->name)
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ $client->name }}</p><h1>Instalaciones de producción</h1><p class="muted">Registrá cada sistema y su base MySQL/MariaDB con un usuario de solo lectura.</p></div><a class="button" href="{{ route('clients.index') }}">Volver a clientes</a></div>
<section class="panel">
    <div class="panel-heading"><div><h2>Sistemas registrados</h2><p>Guardar los datos no comprueba la conexión. Las métricas y el ingreso de soporte aún no están habilitados.</p></div></div>
    @forelse ($installations as $installation)
        <article class="client-card">
            <h3>{{ $installation->name }}</h3>
            <p>{{ $installation->url }}</p>
            <p class="muted">Conexión pendiente de verificación · Credenciales guardadas cifradas</p>
            <div class="client-actions"><a class="button button-small" href="{{ $installation->url }}" target="_blank" rel="noopener noreferrer">Abrir sistema</a><form method="post" action="{{ route('clients.installations.destroy', [$client, $installation]) }}" data-confirm="¿Eliminar esta instalación y sus credenciales guardadas?">@csrf @method('DELETE')<button class="button button-small" type="submit">Eliminar instalación</button></form></div>
        </article>
    @empty <div class="empty-state">Todavía no hay instalaciones cargadas.</div> @endforelse
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Agregar instalación</h2><p>La contraseña no se muestra ni se recupera en el formulario.</p></div></div>
    <form method="post" action="{{ route('clients.installations.store', $client) }}">
        @csrf
        <div class="form-grid">
            <label class="field"><span>Nombre del sistema o sucursal</span><input name="name" maxlength="160" value="{{ old('name') }}" required></label>
            <label class="field"><span>URL del sistema (HTTPS)</span><input name="url" type="url" maxlength="2048" placeholder="https://sistema.ejemplo.com" value="{{ old('url') }}" required></label>
            <label class="field"><span>Host de la base</span><input name="host" maxlength="255" value="{{ old('host') }}" required></label>
            <label class="field"><span>Puerto</span><input name="port" type="number" min="1" max="65535" value="{{ old('port', 3306) }}" required></label>
            <label class="field"><span>Base de datos</span><input name="database" maxlength="255" value="{{ old('database') }}" required></label>
            <label class="field"><span>Usuario de solo lectura</span><input name="username" maxlength="255" autocomplete="off" value="{{ old('username') }}" required></label>
            <label class="field"><span>Contraseña de la base</span><input name="password" type="password" maxlength="4096" autocomplete="new-password" required></label>
        </div>
        <div class="modal-actions"><button class="button button-primary" type="submit">Guardar instalación</button></div>
    </form>
</section>
@endsection
