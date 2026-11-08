@extends('layouts.app')
@section('title', 'Productos | DevFest Piura')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><p class="text-uppercase text-secondary small fw-semibold mb-1">Catálogo</p><h1 class="h3 mb-1">Productos y servicios</h1><p class="text-secondary mb-0">Administra los conceptos disponibles para las ventas.</p></div>
</div>
<div class="row g-4">
    <div class="col-lg-4"><section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5 mb-3">Agregar concepto</h2>
        <form method="post" action="{{ route('products.store') }}">@csrf
            <div class="mb-3"><label class="form-label" for="code">Código <span class="text-secondary fw-normal">(opcional)</span></label><input class="form-control" id="code" name="code" value="{{ old('code') }}" maxlength="40"><div class="form-text">Se asignará automáticamente si lo dejas vacío.</div></div>
            <div class="mb-3"><label class="form-label" for="name">Descripción</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="180"></div>
            <div class="row g-3"><div class="col-5"><label class="form-label" for="unit_code">Unidad</label><select class="form-select" id="unit_code" name="unit_code"><option value="NIU">Unidad</option><option value="ZZ">Servicio</option><option value="KGM">Kilogramo</option><option value="LTR">Litro</option></select></div><div class="col-7"><label class="form-label" for="unit_price">Precio sin IGV (S/)</label><input class="form-control" id="unit_price" name="unit_price" type="number" min="0.01" step="0.01" value="{{ old('unit_price') }}" required></div></div>
            <div class="mt-3 mb-4"><label class="form-label" for="tax_rate">IGV</label><select class="form-select" id="tax_rate" name="tax_rate"><option value="18">18 %</option></select></div>
            <button class="btn btn-primary w-100" type="submit">Guardar producto</button>
        </form>
    </div></section></div>
    <div class="col-lg-8"><section class="card border-0 shadow-sm"><div class="card-body p-0"><div class="p-4 border-bottom"><h2 class="h5 mb-0">Catálogo actual</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-4">Código</th><th>Descripción</th><th>Unidad</th><th class="text-end">Precio sin IGV</th><th>Estado</th><th class="text-end pe-4">Acciones</th></tr></thead><tbody>
        @forelse ($products as $product)<tr><td class="ps-4 fw-semibold">{{ $product->code }}</td><td>{{ $product->name }}<div class="text-secondary small">IGV {{ number_format((float) $product->tax_rate, 0) }}%</div></td><td>{{ $product->unit_code }}</td><td class="text-end">S/ {{ number_format((float) $product->unit_price, 2) }}</td><td><span class="badge text-bg-{{ $product->active ? 'success' : 'secondary' }}">{{ $product->active ? 'Activo' : 'Inactivo' }}</span></td><td class="text-end text-nowrap pe-4"><button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProductModal" data-product-id="{{ $product->id }}" data-product-code="{{ $product->code }}" data-product-name="{{ $product->name }}" data-product-unit="{{ $product->unit_code }}" data-product-price="{{ $product->unit_price }}" data-product-tax="{{ $product->tax_rate }}" data-product-active="{{ $product->active ? '1' : '0' }}"><i class="bi bi-pencil" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Editar</span></button> <form class="d-inline" method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('¿Eliminar este producto? Las ventas anteriores conservarán sus datos.');">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Eliminar</span></button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">Aún no hay productos. Agrega el primero desde el formulario.</td></tr>@endforelse
    </tbody></table></div></div></section></div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" id="edit-product-form">@csrf @method('PUT')
            <div class="modal-header"><h2 class="modal-title fs-5" id="editProductTitle">Editar producto</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="edit-code">Código</label><input class="form-control" id="edit-code" name="code" required maxlength="40"></div>
                <div class="mb-3"><label class="form-label" for="edit-name">Descripción</label><input class="form-control" id="edit-name" name="name" required maxlength="180"></div>
                <div class="row g-3"><div class="col-5"><label class="form-label" for="edit-unit">Unidad</label><select class="form-select" id="edit-unit" name="unit_code" required><option value="NIU">Unidad</option><option value="ZZ">Servicio</option><option value="KGM">Kilogramo</option><option value="LTR">Litro</option></select></div><div class="col-7"><label class="form-label" for="edit-price">Precio sin IGV (S/)</label><input class="form-control" id="edit-price" name="unit_price" type="number" min="0.01" step="0.01" required></div></div>
                <div class="mt-3"><label class="form-label" for="edit-tax">IGV</label><select class="form-select" id="edit-tax" name="tax_rate" required><option value="18">18 %</option></select></div>
                <div class="form-check mt-3"><input class="form-check-input" id="edit-active" name="active" type="checkbox" value="1"><label class="form-check-label" for="edit-active">Disponible para nuevas ventas</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
        </form>
    </div></div>
</div>
@endsection
@push('scripts')
<script>
document.querySelector('#editProductModal').addEventListener('show.bs.modal', event => {
    const trigger = event.relatedTarget;
    const form = document.querySelector('#edit-product-form');
    form.action = `/products/${trigger.dataset.productId}`;
    document.querySelector('#edit-code').value = trigger.dataset.productCode;
    document.querySelector('#edit-name').value = trigger.dataset.productName;
    document.querySelector('#edit-unit').value = trigger.dataset.productUnit;
    document.querySelector('#edit-price').value = trigger.dataset.productPrice;
    document.querySelector('#edit-tax').value = trigger.dataset.productTax;
    document.querySelector('#edit-active').checked = trigger.dataset.productActive === '1';
});
</script>
@endpush
