@extends('layouts.app')

@section('title', 'Ventas | DevFest Piura')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <p class="text-uppercase text-secondary small fw-semibold mb-1">Comprobantes</p>
        <h1 class="h3 mb-1">Ventas</h1>
        <p class="text-secondary mb-0">Consulta los comprobantes emitidos y su estado de envío.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('sales.create') }}">Nueva venta</a>
</div>

<section class="card border-0 shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="{{ route('sales.index') }}" class="row g-2 align-items-end">
            <div class="col-md-7 col-lg-8">
                <label class="form-label" for="sales-search">Buscar venta</label>
                <input id="sales-search" class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Número, cliente o documento">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label" for="sales-status">Estado</label>
                <select id="sales-status" class="form-select" name="status">
                    <option value="">Todos</option>
                    @foreach (['draft' => 'Borrador', 'sent' => 'Enviado', 'processing' => 'En proceso', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado', 'error' => 'Error'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1" type="submit">Filtrar</button>
                @if (request()->filled('q') || request()->filled('status'))
                    <a class="btn btn-outline-secondary" href="{{ route('sales.index') }}" aria-label="Limpiar filtros">Limpiar</a>
                @endif
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">Comprobante</th><th>Cliente</th><th>Emisión</th><th class="text-end">Total</th><th>Estado</th><th class="text-end pe-4"></th></tr></thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td class="ps-4 fw-semibold">{{ $document->series }}-{{ $document->number }}</td>
                        <td>{{ $document->customer_name }}<div class="small text-secondary">{{ $document->customer_document_number ?: 'Sin documento' }}</div></td>
                        <td>{{ $document->issue_date->format('d/m/Y') }}</td>
                        <td class="text-end text-nowrap">{{ $document->currency }} {{ number_format((float) $document->total, 2) }}</td>
                        <td><span class="badge text-bg-{{ $document->status === 'accepted' ? 'success' : ($document->status === 'rejected' ? 'danger' : ($document->status === 'error' ? 'secondary' : 'light')) }} {{ ! in_array($document->status, ['accepted', 'rejected', 'error'], true) ? 'text-dark' : '' }}">{{ $document->status_label }}</span></td>
                        <td class="text-end pe-4 text-nowrap"><a href="{{ route('sales.show', $document) }}" class="btn btn-outline-secondary btn-sm">Ver</a> <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ticketPreviewModal" data-pdf-url="{{ route('sales.pdf', $document) }}" data-document-label="{{ $document->series }}-{{ $document->number }}" aria-label="Ver ticket de 80 mm">Imprimir</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">{{ request()->filled('q') || request()->filled('status') ? 'No se encontraron ventas con esos filtros.' : 'Todavía no hay ventas registradas.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($documents->hasPages())
        <div class="card-footer bg-white border-0 px-4 py-3">{{ $documents->links('sales.pagination') }}</div>
    @endif
</section>
@include('sales.partials.pdf-preview')
@endsection
