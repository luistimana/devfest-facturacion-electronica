@extends('layouts.app')

@section('title', 'Venta '.$document->series.'-'.$document->number.' | DevFest Piura')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <p class="text-uppercase text-secondary small fw-semibold mb-1">{{ $document->document_type === '01' ? 'Factura' : 'Boleta de venta' }}</p>
        <h1 class="h3 mb-1">{{ $document->series }}-{{ $document->number }}</h1>
        <p class="text-secondary mb-0">{{ $document->customer_name }} · {{ $document->issue_date->format('d/m/Y') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('sales.index') }}">Volver a ventas</a>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ticketPreviewModal" data-pdf-url="{{ route('sales.pdf', $document) }}" data-document-label="{{ $document->series }}-{{ $document->number }}">Ver ticket 80 mm</button>
        @if (in_array($document->status, ['draft', 'error'], true))
            <form method="post" action="{{ route('sales.send', $document) }}">@csrf<button class="btn btn-primary" type="submit">Enviar a SUNAT</button></form>
        @endif
        @if ($document->ticket && in_array($document->status, ['sent', 'processing'], true))
            <form id="consult-sunat-form" method="post" action="{{ route('sales.consult', $document) }}">
                @csrf
                <button class="btn btn-primary" type="submit" id="consult-sunat-button">
                    <span class="button-label">Consultar estado / CDR</span>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                </button>
            </form>
        @endif
        @if ($document->xml_signed_path)<a class="btn btn-outline-primary" href="{{ route('sales.download', [$document, 'xml']) }}">Descargar XML</a>@endif
        @if ($document->cdr_path)<a class="btn btn-outline-primary" href="{{ route('sales.download', [$document, 'cdr']) }}">Descargar CDR</a>@endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="p-4 border-bottom"><h2 class="h5 mb-0">Detalle de la venta</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-4">Concepto</th><th class="text-end">Cantidad</th><th class="text-end">Precio</th><th class="text-end pe-4">Importe</th></tr></thead>
                        <tbody>
                            @foreach ($document->lines as $line)
                                <tr><td class="ps-4">{{ $line['description'] }}<div class="small text-secondary">{{ $line['code'] }}</div></td><td class="text-end">{{ $line['quantity'] }}</td><td class="text-end">S/ {{ number_format((float) $line['unit_price'], 2) }}</td><td class="text-end pe-4">S/ {{ number_format((float) $line['total'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ms-auto p-4" style="max-width:320px">
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Subtotal</span><span>S/ {{ number_format((float) $document->subtotal, 2) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">IGV</span><span>S/ {{ number_format((float) $document->tax_total, 2) }}</span></div>
                    <div class="d-flex justify-content-between border-top pt-2 fw-semibold"><span>Total</span><span>S/ {{ number_format((float) $document->total, 2) }}</span></div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5">Estado SUNAT</h2>
                <span id="sunat-status-badge" class="badge text-bg-{{ $document->status === 'accepted' ? 'success' : (in_array($document->status, ['rejected', 'error'], true) ? 'danger' : 'secondary') }}">{{ $document->status_label }}</span>
                <p id="sunat-response-message" class="small text-secondary mt-3 mb-1">{{ $document->sunat_response_message ?: 'El comprobante todavía no se ha enviado.' }}</p>
                <div id="sunat-consult-feedback" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>
                @if ($document->sunat_response_code)<div id="sunat-response-code" class="small text-secondary">Código: {{ $document->sunat_response_code }}</div>@else<div id="sunat-response-code" class="small text-secondary d-none"></div>@endif
                @if ($document->ticket)<div class="small text-secondary mt-2">Ticket: {{ $document->ticket }}</div>@endif
                @if (data_get($document->sunat_response, 'notes'))<ul id="sunat-notes" class="small mt-3 mb-0">@foreach (data_get($document->sunat_response, 'notes') as $note)<li>{{ $note }}</li>@endforeach</ul>@else<ul id="sunat-notes" class="small mt-3 mb-0 d-none"></ul>@endif
            </div>
        </section>
        <p class="small text-secondary mt-3">El envío requiere certificado digital y credenciales SOL del emisor.</p>
    </div>
</div>

@include('sales.partials.pdf-preview')
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('#consult-sunat-form');
    if (!form) return;

    const button = document.querySelector('#consult-sunat-button');
    const label = button.querySelector('.button-label');
    const spinner = button.querySelector('.spinner-border');
    const feedback = document.querySelector('#sunat-consult-feedback');
    const message = document.querySelector('#sunat-response-message');
    const statusBadge = document.querySelector('#sunat-status-badge');
    const responseCode = document.querySelector('#sunat-response-code');
    const notesList = document.querySelector('#sunat-notes');

    const consultSunat = async () => {
        button.disabled = true;
        label.textContent = 'Consultando SUNAT…';
        spinner.classList.remove('d-none');
        feedback.className = 'alert alert-info mt-3 mb-0';

        try {
            feedback.textContent = 'Esperando respuesta de SUNAT. Esta consulta puede tardar unos segundos.';
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: new FormData(form),
            });
            const result = await response.json();

            if (!response.ok) throw new Error(result.message || 'No se pudo consultar SUNAT.');

            message.textContent = result.message;
            feedback.className = `alert ${result.status === 'accepted' ? 'alert-success' : (['rejected', 'error'].includes(result.status) ? 'alert-danger' : 'alert-info')} mt-3 mb-0`;
            feedback.textContent = result.status === 'accepted'
                ? 'SUNAT aceptó el comprobante y se obtuvo la CDR.'
                : (result.status === 'processing' ? 'SUNAT continúa procesando el comprobante. Puedes consultar nuevamente más tarde.' : 'Estado recibido de SUNAT.');
            if (result.status_label) statusBadge.textContent = result.status_label;
            if (result.status === 'accepted') statusBadge.className = 'badge text-bg-success';
            else if (['rejected', 'error'].includes(result.status)) statusBadge.className = 'badge text-bg-danger';
            else statusBadge.className = 'badge text-bg-secondary';

            if (result.code) {
                responseCode.textContent = `Código: ${result.code}`;
                responseCode.classList.remove('d-none');
            }
            notesList.replaceChildren(...(result.notes || []).map(note => {
                const item = document.createElement('li');
                item.textContent = note;
                return item;
            }));
            notesList.classList.toggle('d-none', !result.notes?.length);
        } catch (error) {
            feedback.className = 'alert alert-danger mt-3 mb-0';
            feedback.textContent = error.message || 'No se pudo consultar SUNAT. Intenta nuevamente.';
        } finally {
            button.disabled = false;
            label.textContent = 'Consultar estado / CDR';
            spinner.classList.add('d-none');
        }
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        consultSunat();
    });

})();
</script>
@endpush
