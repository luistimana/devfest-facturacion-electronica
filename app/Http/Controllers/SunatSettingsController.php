<?php

namespace App\Http\Controllers;

use App\Models\SunatSetting;
use App\Services\Sunat\DemoCertificateService;
use App\Services\Sunat\SunatSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SunatSettingsController extends Controller
{
    public function edit(SunatSettingsService $settings, DemoCertificateService $demoCertificate): View
    {
        $values = $settings->values();
        $saved = $settings->saved();
        $values['sol_password'] = '';
        $values['has_sol_password'] = filled($saved?->sol_password ?? config('sunat.sol_password'));
        $values['has_certificate_password'] = filled($saved?->certificate_password ?? config('sunat.certificate_password'));
        $values['has_certificate'] = filled($saved?->certificate_path ?? config('sunat.certificate_path'));

        return view('settings.sunat', [
            'settings' => $values,
            'isOnboarding' => blank($saved?->ruc) || blank($saved?->business_name),
            'demoCertificatePrepared' => $demoCertificate->isPrepared(),
        ]);
    }

    public function update(Request $request, SunatSettingsService $settingsService, DemoCertificateService $demoCertificate): RedirectResponse
    {
        $validated = $request->validate([
            'environment' => ['required', 'in:beta,production'],
            'ruc' => ['required', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'business_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:300'],
            'establishment_code' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'soap_endpoint' => ['nullable', 'url', 'max:255'],
            'sol_user' => ['nullable', 'string', 'max:80'],
            'sol_password' => ['nullable', 'string', 'max:255'],
            'certificate' => ['nullable', 'file', 'max:10240', 'mimes:p12,pfx,pem'],
            'certificate_password' => ['nullable', 'string', 'max:255'],
            'invoice_series' => ['required', 'string', 'size:4', 'regex:/^F[A-Z0-9]{3}$/'],
            'receipt_series' => ['required', 'string', 'size:4', 'regex:/^B[A-Z0-9]{3}$/'],
            'timeout' => ['required', 'integer', 'between:10,120'],
        ]);

        $saved = SunatSetting::query()->first();
        $wasOnboarding = blank($saved?->ruc) || blank($saved?->business_name);
        $current = $settingsService->values();
        $host = strtolower((string) parse_url((string) ($validated['soap_endpoint'] ?? ''), PHP_URL_HOST));
        $expectedHost = $validated['environment'] === 'production' ? 'e-factura.sunat.gob.pe' : 'e-beta.sunat.gob.pe';

        if ($host !== '' && ($host !== $expectedHost || parse_url((string) $validated['soap_endpoint'], PHP_URL_SCHEME) !== 'https')) {
            throw ValidationException::withMessages([
                'soap_endpoint' => 'El endpoint debe ser HTTPS y corresponder al entorno seleccionado de SUNAT.',
            ]);
        }

        if ($validated['environment'] === 'production') {
            if (blank($validated['sol_user'] ?? $saved?->sol_user ?? config('sunat.sol_user')) || (blank($validated['sol_password'] ?? null) && blank($saved?->sol_password ?? config('sunat.sol_password')))) {
                throw ValidationException::withMessages(['sol_password' => 'Completa el usuario y la contraseña SOL para producción.']);
            }

            if (! $request->hasFile('certificate') && blank($current['certificate_path'])) {
                throw ValidationException::withMessages(['certificate' => 'Carga el certificado digital del emisor para producción.']);
            }
        }

        if ($request->hasFile('certificate')) {
            $certificatePassword = (string) ($validated['certificate_password'] ?? $saved?->certificate_password ?? '');
            $this->validateCertificate((string) file_get_contents($request->file('certificate')->getRealPath()), $request->file('certificate')->getClientOriginalExtension(), $certificatePassword);
        }

        if ($validated['environment'] === 'beta' && $request->boolean('use_demo_certificate')) {
            $demoCertificate->prepare();
        }

        $setting = $saved ?? new SunatSetting();
        $environment = $validated['environment'];
        $endpoint = trim((string) ($validated['soap_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = $environment === 'production'
                ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService'
                : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        }

        $setting->fill([
            'environment' => $environment,
            'soap_endpoint' => $endpoint,
            'ruc' => $validated['ruc'],
            'business_name' => $validated['business_name'],
            'address' => $validated['address'] ?? null,
            'establishment_code' => $validated['establishment_code'],
            'sol_user' => filled($validated['sol_user'] ?? null) ? $validated['sol_user'] : ($environment === 'beta' ? 'MODDATOS' : null),
            'invoice_series' => strtoupper($validated['invoice_series']),
            'receipt_series' => strtoupper($validated['receipt_series']),
            'timeout' => $validated['timeout'],
        ]);

        if (filled($validated['sol_password'] ?? null)) {
            $setting->sol_password = $validated['sol_password'];
        } elseif ($environment === 'beta' && blank($setting->sol_password)) {
            $setting->sol_password = 'MODDATOS';
        }

        if (filled($validated['certificate_password'] ?? null)) {
            $setting->certificate_password = $validated['certificate_password'];
        }

        if ($request->hasFile('certificate')) {
            $setting->certificate_path = $request->file('certificate')->store('sunat/certificates', 'local');
        }

        $setting->save();

        return $wasOnboarding
            ? redirect()->route('dashboard')->with('status', 'Configuración inicial completada. Ya puedes comenzar a registrar ventas.')
            : redirect()->route('settings.sunat.edit')->with('status', 'Configuración SUNAT actualizada.');
    }

    private function validateCertificate(string $contents, string $extension, string $password): void
    {
        if (in_array(strtolower($extension), ['p12', 'pfx'], true)) {
            $certificates = [];
            if (! openssl_pkcs12_read($contents, $certificates, $password)) {
                throw ValidationException::withMessages(['certificate' => 'No se pudo abrir el certificado. Revisa el archivo y su contraseña.']);
            }
            return;
        }

        $privateKey = openssl_pkey_get_private($contents, $password);
        $certificate = openssl_x509_read($contents);
        if (! $privateKey || ! $certificate) {
            throw ValidationException::withMessages(['certificate' => 'El PEM debe contener una clave privada y un certificado X.509 válidos.']);
        }
    }
}
