<?php

namespace App\Services\Sunat;

use App\Models\SunatSetting;

class SunatSettingsService
{
    public function values(): array
    {
        $stored = SunatSetting::query()->first();
        $environment = $stored?->environment ?: config('sunat.environment', 'beta');
        $defaultEndpoint = $environment === 'production'
            ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService'
            : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';

        return [
            'environment' => $environment,
            'soap_endpoint' => $stored?->soap_endpoint ?: config('sunat.soap_endpoint') ?: $defaultEndpoint,
            'ruc' => $stored?->ruc ?: config('sunat.ruc'),
            'business_name' => $stored?->business_name ?: config('sunat.business_name'),
            'address' => $stored?->address ?: config('sunat.address'),
            'establishment_code' => $stored?->establishment_code ?: config('sunat.establishment_code', '0000'),
            'sol_user' => $stored?->sol_user ?: config('sunat.sol_user') ?: ($environment === 'beta' ? 'MODDATOS' : ''),
            'sol_password' => $stored?->sol_password ?: config('sunat.sol_password') ?: ($environment === 'beta' ? 'MODDATOS' : ''),
            'certificate_path' => $stored?->certificate_path ?: config('sunat.certificate_path'),
            'certificate_password' => $stored?->certificate_password ?: config('sunat.certificate_password'),
            'invoice_series' => $stored?->invoice_series ?: config('sunat.invoice_series', 'F001'),
            'receipt_series' => $stored?->receipt_series ?: config('sunat.receipt_series', 'B001'),
            'timeout' => $stored?->timeout ?: config('sunat.timeout', 40),
        ];
    }

    public function saved(): ?SunatSetting
    {
        return SunatSetting::query()->first();
    }
}
