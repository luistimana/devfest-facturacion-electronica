@extends('layouts.app')

@section('title', 'Configuración SUNAT | DevFest Piura')

@section('content')
@php
    $stepFields = [
        1 => ['ruc', 'business_name', 'address', 'establishment_code', 'invoice_series', 'receipt_series'],
        2 => ['environment', 'soap_endpoint', 'sol_user', 'sol_password', 'timeout'],
        3 => ['certificate', 'certificate_password'],
    ];
    $initialWizardStep = collect($errors->keys())->map(function ($key) use ($stepFields) {
        $field = explode('.', $key)[0];
        foreach ($stepFields as $step => $fields) {
            if (in_array($field, $fields, true)) return $step;
        }
        return null;
    })->first(fn ($step) => $step !== null) ?? 1;
@endphp

<div class="mb-4">
    <p class="text-uppercase text-secondary small fw-semibold mb-1">{{ $isOnboarding ? 'Configuración inicial' : 'Configuración' }}</p>
    <h1 class="h3 mb-1">{{ $isOnboarding ? 'Prepara tu cuenta' : 'Emisor y conexión SUNAT' }}</h1>
    <p class="text-secondary mb-0">{{ $isOnboarding ? 'Completa los pasos para dejar listo tu espacio de facturación.' : 'Actualiza los datos de tu empresa y conexión SUNAT.' }}</p>
</div>

<form id="sunat-settings-form" method="post" action="{{ route('settings.sunat.update') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PUT')
    <input type="hidden" id="use_demo_certificate" name="use_demo_certificate" value="{{ old('use_demo_certificate', $demoCertificatePrepared ? '1' : '0') }}">

    <div class="card border shadow-sm p-2 p-md-3 mb-4">
        <div class="small text-uppercase text-secondary fw-semibold px-2 mb-2">Pasos de configuración</div>
        <ul class="nav nav-pills flex-column flex-sm-row gap-1" role="tablist" aria-label="Pasos de configuración SUNAT">
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 text-sm-start {{ $initialWizardStep === 1 ? 'active shadow-sm' : 'text-secondary' }}" id="sunat-tab-1" type="button" role="tab" aria-controls="sunat-step-1" aria-selected="{{ $initialWizardStep === 1 ? 'true' : 'false' }}" data-step-target="1"><span class="badge rounded-pill me-2 {{ $initialWizardStep === 1 ? 'text-bg-light text-primary' : 'text-bg-secondary' }}">1</span>Empresa</button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 text-sm-start {{ $initialWizardStep === 2 ? 'active shadow-sm' : 'text-secondary' }}" id="sunat-tab-2" type="button" role="tab" aria-controls="sunat-step-2" aria-selected="{{ $initialWizardStep === 2 ? 'true' : 'false' }}" data-step-target="2"><span class="badge rounded-pill me-2 {{ $initialWizardStep === 2 ? 'text-bg-light text-primary' : 'text-bg-secondary' }}">2</span>Acceso a SUNAT</button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 text-sm-start {{ $initialWizardStep === 3 ? 'active shadow-sm' : 'text-secondary' }}" id="sunat-tab-3" type="button" role="tab" aria-controls="sunat-step-3" aria-selected="{{ $initialWizardStep === 3 ? 'true' : 'false' }}" data-step-target="3"><span class="badge rounded-pill me-2 {{ $initialWizardStep === 3 ? 'text-bg-light text-primary' : 'text-bg-secondary' }}">3</span>Certificado digital</button>
            </li>
        </ul>
    </div>

    <div class="card border-0 shadow-sm">
        <section class="card-body p-4 p-lg-5 {{ $initialWizardStep === 1 ? '' : 'd-none' }}" id="sunat-step-1" data-wizard-step="1" role="tabpanel" aria-labelledby="sunat-tab-1" tabindex="0">
            <div class="mb-4">
                <p class="text-uppercase text-secondary small fw-semibold mb-1">Paso 1 de 3</p>
                <h2 class="h4 mb-1" tabindex="-1">Datos del emisor</h2>
                <p class="text-secondary mb-0">Información de la empresa que emitirá los comprobantes.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label" for="ruc">RUC de tu empresa</label><input class="form-control" id="ruc" name="ruc" inputmode="numeric" maxlength="11" value="{{ old('ruc', $settings['ruc']) }}" required><div class="form-text">Es el RUC que emite el comprobante, no el RUC del cliente.</div></div>
                <div class="col-md-7"><label class="form-label" for="business_name">Razón social</label><input class="form-control" id="business_name" name="business_name" maxlength="200" value="{{ old('business_name', $settings['business_name']) }}" required></div>
                <div class="col-12"><label class="form-label" for="address">Dirección fiscal</label><input class="form-control" id="address" name="address" maxlength="300" value="{{ old('address', $settings['address']) }}"></div>
                <div class="col-md-4"><label class="form-label" for="establishment_code">Código de local anexo</label><input class="form-control" id="establishment_code" name="establishment_code" inputmode="numeric" maxlength="4" value="{{ old('establishment_code', $settings['establishment_code']) }}" required><div class="form-text">Por defecto: 0000 (establecimiento principal).</div></div>
                <div class="col-md-4"><label class="form-label" for="invoice_series">Serie de facturas</label><input class="form-control text-uppercase" id="invoice_series" name="invoice_series" maxlength="4" value="{{ old('invoice_series', $settings['invoice_series']) }}" required></div>
                <div class="col-md-4"><label class="form-label" for="receipt_series">Serie de boletas</label><input class="form-control text-uppercase" id="receipt_series" name="receipt_series" maxlength="4" value="{{ old('receipt_series', $settings['receipt_series']) }}" required></div>
            </div>
        </section>

        <section class="card-body p-4 p-lg-5 d-none" id="sunat-step-2" data-wizard-step="2" role="tabpanel" aria-labelledby="sunat-tab-2" tabindex="0">
            <div class="mb-4">
                <p class="text-uppercase text-secondary small fw-semibold mb-1">Paso 2 de 3</p>
                <h2 class="h4 mb-1" tabindex="-1">Acceso a SUNAT</h2>
                <p class="text-secondary mb-0">Selecciona el entorno y completa las credenciales de conexión.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="environment">Entorno</label><select class="form-select" id="environment" name="environment"><option value="beta" @selected(old('environment', $settings['environment']) === 'beta')>BETA · pruebas</option><option value="production" @selected(old('environment', $settings['environment']) === 'production')>Producción</option></select></div>
                <div class="col-md-8"><label class="form-label" for="soap_endpoint">Endpoint SOAP</label><input class="form-control" id="soap_endpoint" name="soap_endpoint" type="url" value="{{ old('soap_endpoint', $settings['soap_endpoint']) }}" required></div>
                <div class="col-md-6"><label class="form-label" for="sol_user">Usuario SOL</label><input class="form-control" id="sol_user" name="sol_user" value="{{ old('sol_user', $settings['sol_user']) }}" autocomplete="off"><div class="form-text">Ingresa solo el usuario; el RUC se antepone automáticamente. BETA utiliza MODDATOS.</div></div>
                <div class="col-md-6"><label class="form-label" for="sol_password">Clave SOL</label><input class="form-control" id="sol_password" name="sol_password" type="password" autocomplete="new-password" placeholder="{{ $settings['has_sol_password'] ? 'Guardada; escribe solo si deseas cambiarla' : ($settings['environment'] === 'beta' ? 'BETA usa MODDATOS automáticamente' : 'Clave SOL') }}"><div class="form-text">La clave se cifra antes de guardarse.</div></div>
                <div class="col-md-6"><label class="form-label" for="timeout">Tiempo límite de conexión (segundos)</label><input class="form-control" id="timeout" name="timeout" type="number" min="10" max="120" value="{{ old('timeout', $settings['timeout']) }}" required></div>
                <div class="col-12"><button class="btn btn-outline-secondary btn-sm" id="beta-defaults" type="button">Cargar valores oficiales BETA</button></div>
            </div>
            <div class="alert alert-info mt-4 mb-0 small"><strong>BETA:</strong> entorno exclusivo para pruebas. Puedes usar el certificado demo del siguiente paso. <strong>Producción:</strong> usa la clave SOL y el certificado digital real de tu empresa.</div>
        </section>

        <section class="card-body p-4 p-lg-5 d-none" id="sunat-step-3" data-wizard-step="3" role="tabpanel" aria-labelledby="sunat-tab-3" tabindex="0">
            <div class="mb-4">
                <p class="text-uppercase text-secondary small fw-semibold mb-1">Paso 3 de 3</p>
                <h2 class="h4 mb-1" tabindex="-1">Certificado digital</h2>
                <p class="text-secondary mb-0">Usa el certificado demo en BETA o carga el certificado de tu empresa para producción.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><button class="btn btn-outline-secondary btn-sm" id="prepare-demo-certificate" type="button">{{ $demoCertificatePrepared ? 'Certificado demo listo' : 'Preparar certificado demo' }}</button><span class="small text-secondary" id="demo-certificate-status">{{ $demoCertificatePrepared ? 'Seleccionado para BETA.' : 'Solo para pruebas en BETA.' }}</span></div>
            <div class="mb-3"><label class="form-label" for="certificate">Archivo del certificado</label><input class="form-control" id="certificate" name="certificate" type="file" accept=".p12,.pfx,.pem"><div class="form-text">{{ $settings['has_certificate'] ? 'Hay un certificado cargado. Deja este campo vacío para conservarlo.' : 'No hay certificado cargado.' }}</div></div>
            <div><label class="form-label" for="certificate_password">Contraseña del certificado</label><input class="form-control" id="certificate_password" name="certificate_password" type="password" autocomplete="new-password" placeholder="{{ $settings['has_certificate_password'] ? 'Guardada; escribe solo si deseas cambiarla' : 'Contraseña del P12/PFX' }}"></div>
        </section>

        <div class="card-footer bg-white border-0 px-4 px-lg-5 pb-4 pb-lg-5">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <span class="small text-secondary" id="wizard-step-status" aria-live="polite">Paso {{ $initialWizardStep }} de 3</span>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary d-none" id="wizard-previous" type="button">Anterior</button>
                    <button class="btn btn-primary" id="wizard-next" type="button">Continuar</button>
                    <button class="btn btn-success d-none" id="wizard-save" type="submit">{{ $isOnboarding ? 'Finalizar configuración' : 'Guardar cambios' }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('#sunat-settings-form');
    const tabs = [...document.querySelectorAll('[data-step-target]')];
    const steps = [...document.querySelectorAll('[data-wizard-step]')];
    const previous = document.querySelector('#wizard-previous');
    const next = document.querySelector('#wizard-next');
    const save = document.querySelector('#wizard-save');
    const status = document.querySelector('#wizard-step-status');
    let currentStep = Number(@js($initialWizardStep));

    const showStep = (step, focus = false) => {
        currentStep = step;
        steps.forEach(section => section.classList.toggle('d-none', Number(section.dataset.wizardStep) !== step));
        tabs.forEach(tab => {
            const active = Number(tab.dataset.stepTarget) === step;
            const badge = tab.querySelector('.badge');
            tab.classList.toggle('active', active);
            tab.classList.toggle('shadow-sm', active);
            tab.classList.toggle('text-secondary', !active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            badge.classList.toggle('text-bg-light', active);
            badge.classList.toggle('text-primary', active);
            badge.classList.toggle('text-bg-secondary', !active);
        });
        previous.classList.toggle('d-none', step === 1);
        next.classList.toggle('d-none', step === steps.length);
        save.classList.toggle('d-none', step !== steps.length);
        status.textContent = `Paso ${step} de ${steps.length}`;

        if (focus) {
            const heading = steps[step - 1].querySelector('h2');
            heading?.focus({preventScroll: true});
            steps[step - 1].scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    };

    const firstInvalidField = step => [...steps[step - 1].querySelectorAll('input, select, textarea')]
        .find(field => field.willValidate && !field.checkValidity());

    form.noValidate = true;
    tabs.forEach(tab => tab.addEventListener('click', () => showStep(Number(tab.dataset.stepTarget), true)));
    previous.addEventListener('click', () => showStep(Math.max(1, currentStep - 1), true));
    next.addEventListener('click', () => {
        const invalid = firstInvalidField(currentStep);
        if (invalid) { invalid.reportValidity(); invalid.focus(); return; }
        showStep(Math.min(steps.length, currentStep + 1), true);
    });
    form.addEventListener('submit', event => {
        for (let step = 1; step <= steps.length; step++) {
            const invalid = firstInvalidField(step);
            if (invalid) {
                event.preventDefault();
                showStep(step, true);
                invalid.reportValidity();
                invalid.focus();
                return;
            }
        }
    });

    document.querySelector('#beta-defaults')?.addEventListener('click', () => {
        document.querySelector('#environment').value = 'beta';
        document.querySelector('#soap_endpoint').value = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        document.querySelector('#sol_user').value = 'MODDATOS';
        document.querySelector('#sol_password').value = 'MODDATOS';
    });

    document.querySelector('#prepare-demo-certificate')?.addEventListener('click', () => {
        document.querySelector('#environment').value = 'beta';
        document.querySelector('#soap_endpoint').value = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        document.querySelector('#sol_user').value = 'MODDATOS';
        document.querySelector('#sol_password').value = 'MODDATOS';
        document.querySelector('#use_demo_certificate').value = '1';
        document.querySelector('#demo-certificate-status').textContent = 'Certificado PEM demo seleccionado; se guardará en privado al finalizar.';
    });

    document.querySelector('#environment')?.addEventListener('change', event => {
        const endpoint = document.querySelector('#soap_endpoint');
        const solUser = document.querySelector('#sol_user');
        const solPassword = document.querySelector('#sol_password');
        if (event.target.value === 'production') {
            endpoint.value = 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';
            if (solUser.value === 'MODDATOS') solUser.value = '';
            if (solPassword.value === 'MODDATOS') solPassword.value = '';
        } else {
            endpoint.value = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
            if (!solUser.value) solUser.value = 'MODDATOS';
            if (!solPassword.value && !{{ $settings['has_sol_password'] ? 'true' : 'false' }}) solPassword.value = 'MODDATOS';
        }
    });

    showStep(currentStep);
})();
</script>
@endpush
