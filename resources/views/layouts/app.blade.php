<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'DevFest Piura')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}?v={{ filemtime(public_path('vendor/bootstrap/css/bootstrap.min.css')) }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ filemtime(public_path('vendor/bootstrap-icons/bootstrap-icons.css')) }}">
</head>
<body class="bg-body-tertiary">
@auth
    <div class="d-lg-flex min-vh-100">
        <aside class="offcanvas-lg offcanvas-start sticky-top vh-100 flex-shrink-0 p-0" tabindex="-1" id="mainSidebar" aria-labelledby="sidebarLabel" style="--bs-offcanvas-width: 260px">
            <div class="text-bg-dark d-flex flex-column h-100 overflow-y-auto p-3 p-xl-4">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-4 pb-4 border-bottom border-secondary border-opacity-50">
                    <a class="d-flex align-items-center gap-3 text-white text-decoration-none" href="{{ route('dashboard') }}">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-warning text-dark fw-bold fs-5 px-3 py-2">D</span>
                        <span><strong id="sidebarLabel" class="d-block">DevFest Piura</strong><small class="text-white-50">Facturación electrónica</small></span>
                    </a>
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
                </div>

                <div class="small text-uppercase text-white-50 fw-semibold px-2 mb-2">Menú principal</div>
                <nav class="nav nav-pills flex-column gap-1" aria-label="Navegación principal">
                    <a class="nav-link d-flex align-items-center gap-3 rounded-3 px-3 py-2 {{ request()->routeIs('dashboard') ? 'active shadow-sm' : 'text-white-50' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                        <i class="bi bi-house fs-5" aria-hidden="true"></i><span>Inicio</span>
                    </a>
                    <a class="nav-link d-flex align-items-center gap-3 rounded-3 px-3 py-2 {{ request()->routeIs('sales.*') && !request()->routeIs('sales.create') ? 'active shadow-sm' : 'text-white-50' }}" href="{{ route('sales.index') }}" @if (request()->routeIs('sales.*') && !request()->routeIs('sales.create')) aria-current="page" @endif>
                        <i class="bi bi-receipt fs-5" aria-hidden="true"></i><span>Ventas</span>
                    </a>
                    <a class="nav-link d-flex align-items-center gap-3 rounded-3 px-3 py-2 {{ request()->routeIs('products.*') ? 'active shadow-sm' : 'text-white-50' }}" href="{{ route('products.index') }}" @if (request()->routeIs('products.*')) aria-current="page" @endif>
                        <i class="bi bi-box-seam fs-5" aria-hidden="true"></i><span>Productos</span>
                    </a>
                    <a class="nav-link d-flex align-items-center gap-3 rounded-3 px-3 py-2 {{ request()->routeIs('sales.create') ? 'active shadow-sm' : 'text-white-50' }}" href="{{ route('sales.create') }}" @if (request()->routeIs('sales.create')) aria-current="page" @endif>
                        <i class="bi bi-plus-circle fs-5" aria-hidden="true"></i><span>Nueva venta</span>
                    </a>
                    <a class="nav-link d-flex align-items-center gap-3 rounded-3 px-3 py-2 {{ request()->routeIs('settings.*') ? 'active shadow-sm' : 'text-white-50' }}" href="{{ route('settings.sunat.edit') }}" @if (request()->routeIs('settings.*')) aria-current="page" @endif>
                        <i class="bi bi-gear fs-5" aria-hidden="true"></i><span>Configuración</span>
                    </a>
                </nav>

                
            </div>
        </aside>
        <div class="flex-grow-1 min-vh-100">
            <header class="navbar bg-white border-bottom sticky-top px-3 px-lg-4">
                <button class="btn btn-dark d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainSidebar" aria-controls="mainSidebar" aria-label="Abrir menú"><i class="bi bi-list" aria-hidden="true"></i></button>
                <span class="navbar-brand mb-0 fs-6"><strong>DevFest Piura</strong><span class="text-secondary fw-normal"> / {{ trim($__env->yieldContent('title', 'Comprobantes')) }}</span></span>
                <div class="dropdown ms-auto">
                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1" aria-hidden="true"></i>{{ auth()->user()->name }}</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-secondary">Sesión iniciada</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar sesión</button></form></li>
                    </ul>
                </div>
            </header>
            <main class="container-fluid px-3 px-lg-4 py-4">
            @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            </main>
        </div>
    </div>
@else
    <main class="container py-4 py-lg-5">
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
@endauth
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
