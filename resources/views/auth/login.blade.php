@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<main class="login-wrap">
    <div class="login-card">
        <a class="brand login-brand" href="{{ route('login') }}"><span class="brand-mark">$</span><span><b>Mis cuentas</b><small>Tu espacio privado</small></span></a>
        <h1>Hola de nuevo</h1><p class="muted">Ingresá para administrar tus clientes y tus finanzas.</p>
        @if ($errors->any()) <div class="flash error">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ route('login.store') }}" class="form-stack">
            @csrf
            <label class="field"><span>Correo electrónico</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label class="field"><span>Contraseña</span><input type="password" name="password" autocomplete="current-password" required></label>
            <label class="check-line"><input type="checkbox" name="remember" value="1"><span>Mantener la sesión iniciada</span></label>
            <button class="button button-primary button-large" type="submit">Ingresar</button>
        </form>
        <p class="login-foot">El acceso se crea desde el servidor. No hay registro público.</p>
    </div>
</main>
@endsection
