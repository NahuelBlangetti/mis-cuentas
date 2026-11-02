<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f6f2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset_version('icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset_version('icon-180.png') }}">
    <link rel="stylesheet" href="{{ asset_version('css/app.css') }}">
    <title>@yield('title', 'Mis cuentas') · Mis cuentas</title>
</head>
<body>
@auth
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">$</span><span><b>Mis cuentas</b><small>Clientes y finanzas</small></span></a>
        <div class="nav-caption">Administración</div>
        <nav class="side-nav" aria-label="Navegación principal">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span>Resumen</a>
            <a class="{{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}"><span>♧</span>Clientes y cobros</a>
            <a class="{{ request()->routeIs('movements.*') ? 'active' : '' }}" href="{{ route('movements.index') }}"><span>↕</span>Movimientos</a>
            <a class="{{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}"><span>◷</span>Finanzas personales</a>
        </nav>
        <div class="sidebar-spacer"></div>
        <div class="account-card"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="account-name"><b>{{ auth()->user()->name }}</b><small>Cuenta privada</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Cerrar sesión" aria-label="Cerrar sesión">↗</button></form></div>
    </aside>
    <div class="main-column">
        <header class="mobile-header"><a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">$</span><b>Mis cuentas</b></a><form method="post" action="{{ route('logout') }}">@csrf<button class="button button-quiet" type="submit">Salir</button></form></header>
        <main class="main-content">
            @if (session('success')) <div class="flash success" role="status">{{ session('success') }}</div> @endif
            @if ($errors->any()) <div class="flash error" role="alert">Revisá los datos: {{ $errors->first() }}</div> @endif
            @yield('content')
        </main>
        <nav class="bottom-nav" aria-label="Navegación móvil">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><small>Inicio</small></a>
            <a class="{{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}"><span>♧</span><small>Clientes</small></a>
            <a class="{{ request()->routeIs('movements.*') ? 'active' : '' }}" href="{{ route('movements.index') }}"><span>↕</span><small>Movimientos</small></a>
            <a class="{{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}"><span>◷</span><small>Personal</small></a>
        </nav>
    </div>
</div>
@else
    @yield('content')
@endauth
<script src="{{ asset_version('js/app.js') }}" defer></script>
</body>
</html>
