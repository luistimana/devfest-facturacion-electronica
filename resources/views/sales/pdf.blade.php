<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document->series }}-{{ $document->number }}</title>
    <style>
        @page{margin:3mm}
        *{box-sizing:border-box}
        body{font-family:DejaVu Sans,sans-serif;color:#111827;font-size:7.5pt;line-height:1.25;margin:0}
        p{margin:0}
        .center{text-align:center}
        .muted{color:#555}
        .company{padding:0 0 5px;text-align:center}
        .company-name{font-size:10pt;font-weight:bold;margin-bottom:3px}
        .company-line{font-size:7pt;margin:1px 0;overflow-wrap:anywhere}
        .document-box{border-top:1px solid #9ca3af;border-bottom:1px solid #9ca3af;margin:4px 0 6px;padding:5px 2px;text-align:center}
        .document-type{font-size:8pt;font-weight:bold}
        .document-number{font-size:9pt;font-weight:bold;margin-top:2px}
        .meta,.items,.totals{border-collapse:collapse;width:100%}
        .meta{margin:2px 0 5px}
        .meta td{padding:1.5px 0;vertical-align:top}
        .meta td:first-child{font-weight:bold;padding-right:4px;width:37%}
        .section-label{border-top:1px solid #9ca3af;border-bottom:1px solid #9ca3af;font-size:7pt;font-weight:bold;margin-top:4px;padding:3px 0}
        .customer{border-bottom:1px solid #d1d5db;margin-bottom:3px;padding:3px 0}
        .customer-row{padding:1px 0;overflow-wrap:anywhere}
        .items th,.items td{padding:3px 1px;vertical-align:top}
        .items th{border-bottom:1px solid #6b7280;font-size:6.5pt;text-align:left}
        .items tbody tr+tr td{border-top:1px solid #e5e7eb}
        .items .qty{width:10%;text-align:center;white-space:nowrap}
        .items .description{width:49%;overflow-wrap:anywhere}
        .items .unit-price{width:19%;text-align:right;white-space:nowrap}
        .items .amount{width:22%;text-align:right;white-space:nowrap}
        .unit-description{color:#555;font-size:6.5pt;margin-top:1px}
        .totals{margin-top:5px}
        .totals td{padding:2px 3px}
        .totals .label{background:#f3f4f6;font-weight:bold;width:58%}
        .totals .amount{text-align:right;white-space:nowrap}
        .totals .grand td{border-top:1px solid #6b7280;font-size:8.5pt;font-weight:bold;padding-top:4px}
        .amount-words{font-size:6.5pt;margin-top:6px;overflow-wrap:anywhere}
        .qr{margin:7px auto 3px;text-align:center}
        .qr img{height:76px;width:76px}
        .hash{font-size:5.5pt;overflow-wrap:anywhere;text-align:center}
        .footer{border-top:1px dashed #9ca3af;color:#555;font-size:6.5pt;margin-top:6px;padding-top:5px;text-align:center}
    </style>
</head>
<body>
    <header class="company">
        <div class="company-name">{{ $companyName }}</div>
        @if ($companyRuc)<p class="company-line">RUC: {{ $companyRuc }}</p>@endif
        @if ($companyAddress)<p class="company-line">{{ $companyAddress }}</p>@endif
    </header>

    <div class="document-box">
        <div class="document-type">{{ $document->document_type === '01' ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA' }}</div>
        <div class="document-number">{{ $document->series }}-{{ str_pad((string) $document->number, 8, '0', STR_PAD_LEFT) }}</div>
    </div>

    <table class="meta">
        <tr><td>Fecha emisión</td><td>{{ $document->issue_date->format('d/m/Y') }}{{ $document->sent_at ? ' '.$document->sent_at->format('H:i:s') : '' }}</td></tr>
        @if ($document->payment_due_date)<tr><td>Fecha vencimiento</td><td>{{ $document->payment_due_date->format('d/m/Y') }}</td></tr>@endif
        <tr><td>Forma de pago</td><td>{{ $document->payment_condition === 'credit' ? 'Crédito' : 'Contado' }}</td></tr>
        <tr><td>Moneda</td><td>{{ $document->currency === 'PEN' ? 'Soles' : $document->currency }}</td></tr>
    </table>

    <div class="section-label">CLIENTE</div>
    <div class="customer">
        <div class="customer-row"><strong>Nombre</strong> &nbsp; {{ $document->customer_name }}</div>
        <div class="customer-row">
            <strong>{{ $document->customer_document_type === '1' ? 'DNI' : ($document->customer_document_type === '6' ? 'RUC' : 'Documento') }}</strong>
            &nbsp; {{ $document->customer_document_number ?: '00000000' }}
        </div>
        @if ($document->customer_address)<div class="customer-row"><strong>Dirección</strong> &nbsp; {{ $document->customer_address }}</div>@endif
    </div>

    <table class="items">
        <thead><tr><th class="qty">Cant.</th><th class="description">Descripción</th><th class="unit-price">P.Unit</th><th class="amount">Imp.</th></tr></thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $line['quantity'], 2, '.', ''), '0'), '.') }}</td>
                    <td class="description">{{ $line['description'] }}<div class="unit-description">{{ $line['unit_code'] ?? '' }}</div></td>
                    <td class="unit-price">{{ $document->currency === 'PEN' ? 'S/' : $document->currency }} {{ number_format((float) $line['unit_price'], 2) }}</td>
                    <td class="amount">{{ $document->currency === 'PEN' ? 'S/' : $document->currency }} {{ number_format((float) $line['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">OP. GRAVADAS</td><td class="amount">{{ $document->currency === 'PEN' ? 'S/' : $document->currency }} {{ number_format((float) $document->subtotal, 2) }}</td></tr>
        <tr><td class="label">IGV ({{ $document->subtotal > 0 ? number_format(((float) $document->tax_total / (float) $document->subtotal) * 100, 2) : '0.00' }}%)</td><td class="amount">{{ $document->currency === 'PEN' ? 'S/' : $document->currency }} {{ number_format((float) $document->tax_total, 2) }}</td></tr>
        <tr class="grand"><td>TOTAL</td><td class="amount">{{ $document->currency === 'PEN' ? 'S/' : $document->currency }} {{ number_format((float) $document->total, 2) }}</td></tr>
    </table>

    @if ($qrBase64)
        <div class="qr"><img src="data:image/png;base64,{{ $qrBase64 }}" alt="Código QR SUNAT"></div>
        @if ($document->signature_hash)<p class="hash"><strong>Hash:</strong> {{ $document->signature_hash }}</p>@endif
    @elseif ($document->status === 'draft')
        <p class="center muted" style="font-size:6pt;margin-top:6px">El QR estará disponible al firmar el comprobante.</p>
    @endif

    <div class="footer">Representación impresa del comprobante electrónico.<br>Consulte su estado en DevFest Piura.</div>
</body>
</html>
