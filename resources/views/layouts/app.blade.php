<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f3faf7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset_version('icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset_version('icon-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset_version('css/app.css') }}">
    <title>@yield('title', 'Mis cuentas') · Mis cuentas</title>
</head>
<body>
@auth
@php($moreActive = request()->routeIs('fixed-expenses.*', 'infrastructure.*'))
<a class="skip" href="#contenido">Saltar al contenido</a>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">M</span>
            <span><b>Mis cuentas</b><small>Clientes y finanzas</small></span>
        </a>
        <nav class="side-nav" aria-label="Navegación principal">
            <p class="nav-caption">Día a día</p>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="home" />Resumen</a>
            <a class="{{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}" @if(request()->routeIs('clients.*')) aria-current="page" @endif><x-icon name="clients" />Clientes y cobros</a>
            <a class="{{ request()->routeIs('movements.*') ? 'active' : '' }}" href="{{ route('movements.index') }}" @if(request()->routeIs('movements.*')) aria-current="page" @endif><x-icon name="list" />Movimientos</a>
            <p class="nav-caption">Costos</p>
            <a class="{{ request()->routeIs('fixed-expenses.*') ? 'active' : '' }}" href="{{ route('fixed-expenses.index') }}" @if(request()->routeIs('fixed-expenses.*')) aria-current="page" @endif><x-icon name="repeat" />Gastos fijos</a>
            <a class="{{ request()->routeIs('infrastructure.*') ? 'active' : '' }}" href="{{ route('infrastructure.index') }}" @if(request()->routeIs('infrastructure.*')) aria-current="page" @endif><x-icon name="server" />Infraestructura</a>
            <p class="nav-caption">Personal</p>
            <a class="{{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}" @if(request()->routeIs('personal.*')) aria-current="page" @endif><x-icon name="wallet" />Finanzas personales</a>
        </nav>
        <div class="sidebar-spacer"></div>
        <div class="account-card">
            <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
            <span class="account-name"><b>{{ auth()->user()->name }}</b><small>Cuenta privada</small></span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-quiet" type="submit">Salir</button></form>
        </div>
    </aside>
    <div class="main-column">
        <header class="mobile-header">
            <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">M</span><b>Mis cuentas</b></a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-quiet" type="submit">Salir</button></form>
        </header>
        <main class="main-content" id="contenido">
            @if (session('success')) <div class="flash success" role="status">{{ session('success') }}</div> @endif
            @if ($errors->any()) <div class="flash error" role="alert">Revisá los datos: {{ $errors->first() }}</div> @endif
            @yield('content')
        </main>
        <div class="more-sheet" id="more-sheet" hidden>
            <a class="{{ request()->routeIs('fixed-expenses.*') ? 'active' : '' }}" href="{{ route('fixed-expenses.index') }}"><x-icon name="repeat" />Gastos fijos</a>
            <a class="{{ request()->routeIs('infrastructure.*') ? 'active' : '' }}" href="{{ route('infrastructure.index') }}"><x-icon name="server" />Infraestructura</a>
        </div>
        <nav class="bottom-nav" aria-label="Navegación móvil">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="home" /><small>Inicio</small></a>
            <a class="{{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}"><x-icon name="clients" /><small>Clientes</small></a>
            <a class="{{ request()->routeIs('movements.*') ? 'active' : '' }}" href="{{ route('movements.index') }}"><x-icon name="list" /><small>Movimientos</small></a>
            <a class="{{ request()->routeIs('personal.*') ? 'active' : '' }}" href="{{ route('personal.index') }}"><x-icon name="wallet" /><small>Personal</small></a>
            <button class="{{ $moreActive ? 'active' : '' }}" type="button" data-more-toggle aria-expanded="false" aria-controls="more-sheet"><x-icon name="more" /><small>Más</small></button>
        </nav>
    </div>
</div>
@else
    @yield('content')
@endauth
<script src="{{ asset_version('js/app.js') }}" defer></script>
</body>
</html>
