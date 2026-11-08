<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | DevFest Piura</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
</head>
<body class="bg-body-tertiary">
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="card border shadow-sm w-100" style="max-width: 420px">
        <div class="card-body p-4 p-sm-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="d-flex align-items-center justify-content-center rounded bg-warning fw-bold text-dark" style="width:42px;height:42px">DF</span>
                <div><div class="fw-semibold">DevFest Piura</div><div class="small text-secondary">Facturación electrónica</div></div>
            </div>
            <h1 class="h3 mb-1">Iniciar sesión</h1>
            <p class="text-secondary mb-4">Ingresa con tu cuenta para continuar.</p>
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('login.authenticate') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Mantener sesión iniciada</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Ingresar</button>
            </form>
            <p class="small text-secondary text-center mt-4 mb-0">DevFest Piura · {{ date('Y') }}</p>
        </div>
    </section>
</main>
</body>
</html>
