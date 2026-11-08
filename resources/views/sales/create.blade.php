@extends('layouts.app')
@section('title', 'Nueva venta | DevFest Piura')
@section('content')
<div class="mb-4"><p class="text-uppercase text-secondary small fw-semibold mb-1">Ventas</p><h1 class="h3 mb-1">Nueva venta</h1><p class="text-secondary mb-0">Elige los conceptos y completa los datos del cliente.</p></div>
<form method="post" action="{{ route('sales.store') }}" id="sale-form">@csrf
<div class="row g-4"><div class="col-lg-8">
<section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Comprobante</h2><div class="row g-3"><div class="col-md-3"><label class="form-label" for="document_type">Tipo</label><select class="form-select" id="document_type" name="document_type"><option value="03">Boleta de venta</option><option value="01">Factura</option></select></div><div class="col-md-3" id="payment-condition-wrapper"><label class="form-label" for="payment_condition">Forma de pago</label><select class="form-select" id="payment_condition" name="payment_condition"><option value="cash">Contado</option><option value="credit">Crédito</option></select></div><div class="col-md-3 d-none" id="payment-due-wrapper"><label class="form-label" for="payment_due_date">Vence la cuota</label><input class="form-control" type="date" id="payment_due_date" name="payment_due_date" min="{{ now()->toDateString() }}"></div><div class="col-md-3"><label class="form-label" for="customer_document_type">Documento del cliente</label><select class="form-select" id="customer_document_type" name="customer_document_type"><option value="0">Sin documento</option><option value="1">DNI</option><option value="6">RUC</option></select></div><div class="col-md-3" id="customer-document-number-wrapper"><label class="form-label" for="customer_document_number">Número</label><input class="form-control" id="customer_document_number" name="customer_document_number" maxlength="11" inputmode="numeric"></div><div class="col-md-8"><label class="form-label" for="customer_name">Nombre o razón social</label><input class="form-control" id="customer_name" name="customer_name" required maxlength="200"></div><div class="col-md-4"><label class="form-label" for="customer_address">Dirección</label><input class="form-control" id="customer_address" name="customer_address" maxlength="300"></div></div></div></section>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Detalle</h2><button type="button" id="open-product-picker" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#productPickerModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar producto</button></div><div id="sale-lines-error" class="alert alert-warning d-none" role="alert">Agrega al menos un producto o servicio a la venta.</div><div id="sale-lines" class="vstack gap-3"></div><div id="sale-lines-empty" class="text-center text-secondary border rounded-3 p-4"><i class="bi bi-bag-plus fs-3 d-block mb-2" aria-hidden="true"></i>Selecciona productos para agregarlos al detalle.</div><template id="line-template"><div class="sale-line border rounded-3 p-3"><input class="product-id-input" type="hidden" name="items[INDEX][product_id]"><div class="row g-2 align-items-end"><div class="col-md-6"><span class="form-label d-block">Producto o servicio</span><div class="fw-semibold product-name"></div><div class="small text-secondary product-code"></div></div><div class="col-md-2"><label class="form-label">Cantidad</label><input class="form-control quantity-input" name="items[INDEX][quantity]" type="number" min="0.01" step="0.01" value="1" required></div><div class="col-md-3"><label class="form-label">Importe</label><div class="form-control bg-light line-total">S/ 0.00</div></div><div class="col-md-1"><button class="btn btn-outline-secondary remove-line" type="button" aria-label="Quitar línea"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div></div></div></template></div></section>
</div><div class="col-lg-4"><section class="card border-0 shadow-sm sticky-lg-top" style="top:1rem"><div class="card-body p-4"><h2 class="h5 mb-3">Resumen</h2><div class="d-flex justify-content-between mb-2 text-secondary"><span>Subtotal</span><span id="subtotal-view">S/ 0.00</span></div><div class="d-flex justify-content-between mb-3 text-secondary"><span>IGV</span><span id="tax-view">S/ 0.00</span></div><hr><div class="d-flex justify-content-between mb-4 fw-semibold"><span>Total</span><span id="total-view">S/ 0.00</span></div><button class="btn btn-primary w-100" type="submit">Guardar venta</button><p class="small text-secondary mt-3 mb-0">La venta se guardará como borrador. El envío a SUNAT se realiza después de verificar la configuración.</p></div></section></div></div>
</form>
<div class="modal fade" id="productPickerModal" tabindex="-1" aria-labelledby="productPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title fs-5" id="productPickerTitle">Seleccionar productos</h2><p class="small text-secondary mb-0">Marca un producto y agrégalo al detalle.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            @if ($products->isEmpty())
                <div class="alert alert-info mb-0">No hay productos activos. Registra uno en la sección Productos.</div>
            @else
                <div class="mb-3"><label class="form-label" for="product-search">Buscar producto</label><input class="form-control" type="search" id="product-search" placeholder="Código o descripción"></div>
                <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th></th><th>Código y descripción</th><th>Unidad</th><th class="text-end">Precio sin IGV</th></tr></thead><tbody id="product-picker-list">
                    @foreach ($products as $product)
                        <tr data-product-row><td><input class="form-check-input" type="radio" name="selected_product" value="{{ $product->id }}" data-code="{{ $product->code }}" data-name="{{ $product->name }}" data-price="{{ $product->unit_price }}" data-tax="{{ $product->tax_rate }}" aria-label="Seleccionar {{ $product->name }}"></td><td><span class="fw-semibold">{{ $product->code }}</span><div>{{ $product->name }}</div></td><td>{{ $product->unit_code }}</td><td class="text-end text-nowrap">S/ {{ number_format((float) $product->unit_price, 2) }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <div id="product-search-empty" class="text-center text-secondary py-3 d-none">No hay coincidencias.</div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="confirm-add-product" @disabled($products->isEmpty())><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar producto</button></div>
    </div></div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const lines = document.querySelector('#sale-lines');
    const template = document.querySelector('#line-template');
    const pickerModal = document.querySelector('#productPickerModal');
    const emptyMessage = document.querySelector('#sale-lines-empty');
    const noLinesAlert = document.querySelector('#sale-lines-error');
    let nextIndex = 0;
    const money = value => `S/ ${value.toFixed(2)}`;
    const recalculate = () => {
        let subtotal = 0;
        let tax = 0;
        lines.querySelectorAll('.sale-line').forEach(line => {
            const product = line.querySelector('.product-id-input');
            const quantity = Number(line.querySelector('.quantity-input').value || 0);
            const price = Number(product?.dataset.price || 0);
            const rate = Number(product?.dataset.tax || 0) / 100;
            const base = Math.round(price * quantity * 100) / 100;
            const lineTax = Math.round(base * rate * 100) / 100;
            subtotal += base;
            tax += lineTax;
            line.querySelector('.line-total').textContent = money(base + lineTax);
        });
        document.querySelector('#subtotal-view').textContent = money(subtotal);
        document.querySelector('#tax-view').textContent = money(tax);
        document.querySelector('#total-view').textContent = money(subtotal + tax);
    };
    const addLine = product => {
        const html = template.innerHTML.replaceAll('INDEX', String(nextIndex++));
        lines.insertAdjacentHTML('beforeend', html);
        const line = lines.lastElementChild;
        const productInput = line.querySelector('.product-id-input');
        productInput.value = product.id;
        productInput.dataset.price = product.price;
        productInput.dataset.tax = product.tax;
        line.querySelector('.product-name').textContent = product.name;
        line.querySelector('.product-code').textContent = `${product.code} · S/ ${Number(product.price).toFixed(2)} sin IGV`;
        emptyMessage.classList.add('d-none');
        noLinesAlert.classList.add('d-none');
        recalculate();
    };
    const documentType = document.querySelector('#document_type');
    const paymentCondition = document.querySelector('#payment_condition');
    const paymentDue = document.querySelector('#payment_due_date');
    const paymentDueWrapper = document.querySelector('#payment-due-wrapper');
    const customerDocumentType = document.querySelector('#customer_document_type');
    const customerDocumentNumber = document.querySelector('#customer_document_number');
    const customerDocumentNumberWrapper = document.querySelector('#customer-document-number-wrapper');
    const customerName = document.querySelector('#customer_name');
    let customerNameWasEdited = false;
    let customerWasWithoutDocument = false;
    const syncPaymentFields = () => {
        const isInvoice = documentType.value === '01';
        document.querySelector('#payment-condition-wrapper').classList.toggle('d-none', !isInvoice);
        if (!isInvoice) paymentCondition.value = 'cash';
        const isCreditInvoice = isInvoice && paymentCondition.value === 'credit';
        paymentDueWrapper.classList.toggle('d-none', !isCreditInvoice);
        paymentDue.required = isCreditInvoice;
        if (!isCreditInvoice) paymentDue.value = '';
    };
    const syncCustomerFields = () => {
        const isInvoice = documentType.value === '01';
        [...customerDocumentType.options].forEach(option => {
            option.hidden = isInvoice ? option.value !== '6' : option.value === '6';
            option.disabled = option.hidden;
        });
        if (isInvoice) {
            customerDocumentType.value = '6';
        } else if (customerDocumentType.value === '6') {
            customerDocumentType.value = '0';
        }

        const hasNoDocument = customerDocumentType.value === '0';
        customerDocumentNumberWrapper.classList.toggle('d-none', hasNoDocument);
        customerDocumentNumber.required = !hasNoDocument;
        customerDocumentNumber.maxLength = customerDocumentType.value === '1' ? 8 : 11;
        customerDocumentNumber.value = hasNoDocument ? '' : customerDocumentNumber.value;

        if (hasNoDocument && !customerWasWithoutDocument) {
            customerName.value = 'CLIENTES VARIOS';
            customerNameWasEdited = false;
        } else if (!hasNoDocument && customerWasWithoutDocument && !customerNameWasEdited) {
            customerName.value = '';
        }
        customerWasWithoutDocument = hasNoDocument;
    };
    documentType.addEventListener('change', syncPaymentFields);
    paymentCondition.addEventListener('change', syncPaymentFields);
    documentType.addEventListener('change', syncCustomerFields);
    customerDocumentType.addEventListener('change', syncCustomerFields);
    customerName.addEventListener('input', () => { customerNameWasEdited = true; });
    syncPaymentFields();
    syncCustomerFields();
    document.querySelector('#confirm-add-product').addEventListener('click', () => {
        const selected = document.querySelector('input[name="selected_product"]:checked');
        if (!selected) {
            window.alert('Selecciona un producto o servicio.');
            return;
        }
        addLine({ id: selected.value, code: selected.dataset.code, name: selected.dataset.name, price: selected.dataset.price, tax: selected.dataset.tax });
        selected.checked = false;
        bootstrap.Modal.getOrCreateInstance(pickerModal).hide();
    });
    document.querySelector('#product-search')?.addEventListener('input', event => {
        const query = event.target.value.trim().toLocaleLowerCase();
        const rows = [...document.querySelectorAll('[data-product-row]')];
        let visibleCount = 0;
        rows.forEach(row => {
            const visible = row.textContent.toLocaleLowerCase().includes(query);
            row.classList.toggle('d-none', !visible);
            if (visible) visibleCount++;
        });
        document.querySelector('#product-search-empty').classList.toggle('d-none', visibleCount > 0);
    });
    lines.addEventListener('change', recalculate);
    lines.addEventListener('input', recalculate);
    lines.addEventListener('click', event => {
        if (event.target.closest('.remove-line')) {
            event.target.closest('.sale-line').remove();
            emptyMessage.classList.toggle('d-none', lines.children.length > 0);
            recalculate();
        }
    });
    document.querySelector('#sale-form').addEventListener('submit', event => {
        if (lines.children.length === 0) {
            event.preventDefault();
            noLinesAlert.classList.remove('d-none');
            document.querySelector('#open-product-picker').focus();
        }
    });
})();
</script>
@endpush
