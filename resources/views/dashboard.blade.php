@extends('layouts.app')
@section('title', 'Inicio | DevFest Piura')
@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Inicio</h1><p class="text-secondary mb-0">Estado general de tus comprobantes electrónicos.</p></div>
<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Registrados</div><div class="fs-3 fw-semibold mt-2">{{ number_format($documentCount) }}</div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Aceptados por SUNAT</div><div class="fs-3 fw-semibold mt-2">{{ number_format($acceptedCount) }}</div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Pendientes o en proceso</div><div class="fs-3 fw-semibold mt-2">{{ number_format($pendingCount) }}</div></div></div></div>
</div>
<section class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div><h2 class="h5 mb-1">Ventas de los últimos 30 días</h2><p class="small text-secondary mb-0">Importe diario de los comprobantes registrados</p></div>
            <span class="badge rounded-pill text-bg-light border fw-normal">En soles · {{ number_format($salesChart->sum('count')) }} comprobantes</span>
        </div>
        @php($maxSales = max(1, (float) $salesChart->max('total')))
        <svg class="w-100" viewBox="0 0 1200 290" role="img" aria-labelledby="sales-chart-title sales-chart-description">
            <title id="sales-chart-title">Importe de ventas por día, últimos 30 días</title>
            <desc id="sales-chart-description">Total vendido: S/ {{ number_format($salesChart->sum('total'), 2) }} en {{ number_format($salesChart->sum('count')) }} comprobantes.</desc>
            @foreach ([0, 1, 2, 3, 4] as $grid)
                @php($gridY = 225 - ($grid * 40))
                <line x1="54" y1="{{ $gridY }}" x2="1185" y2="{{ $gridY }}" stroke="#dee2e6" stroke-width="1" />
                <text x="48" y="{{ $gridY - 5 }}" text-anchor="end" fill="#6c757d" font-size="11">{{ number_format($maxSales * $grid / 4, 0) }}</text>
            @endforeach
            @foreach ($salesChart as $index => $day)
                @php($barHeight = $day['total'] > 0 ? max(3, (int) round(($day['total'] / $maxSales) * 160)) : 0)
                @php($barX = 62 + ($index * 37.2))
                <g>
                    <title>{{ $day['label'] }}: S/ {{ number_format($day['total'], 2) }} · {{ $day['count'] }} comprobantes</title>
                    @if ($barHeight > 0)
                        <rect x="{{ $barX }}" y="{{ 225 - $barHeight }}" width="22" height="{{ $barHeight }}" rx="4" fill="#0d6efd" />
                    @endif
                    @if ($index % 5 === 0 || $index === 29)
                        <text x="{{ $barX + 11 }}" y="250" text-anchor="middle" fill="#495057" font-size="11">{{ $day['label'] }}</text>
                    @endif
                </g>
            @endforeach
        </svg>
        <div class="d-flex justify-content-between border-top pt-3 mt-1 small text-secondary"><span>Total vendido en soles</span><strong class="text-body">S/ {{ number_format($salesChart->sum('total'), 2) }}</strong></div>
    </div>
</section>
@endsection
