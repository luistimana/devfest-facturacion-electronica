<?php

return [
    'environment' => env('SUNAT_ENVIRONMENT', 'beta'),
    'soap_endpoint' => env('SUNAT_SOAP_ENDPOINT'),
    'ruc' => env('SUNAT_RUC'),
    'business_name' => env('SUNAT_BUSINESS_NAME'),
    'address' => env('SUNAT_ADDRESS'),
    'establishment_code' => env('SUNAT_ESTABLISHMENT_CODE', '0000'),
    'sol_user' => env('SUNAT_SOL_USER'),
    'sol_password' => env('SUNAT_SOL_PASSWORD'),
    'certificate_path' => env('SUNAT_CERTIFICATE_PATH'),
    'certificate_password' => env('SUNAT_CERTIFICATE_PASSWORD'),
    'timeout' => (int) env('SUNAT_TIMEOUT', 40),
    'invoice_series' => env('SUNAT_INVOICE_SERIES', 'F001'),
    'receipt_series' => env('SUNAT_RECEIPT_SERIES', 'B001'),
    'storage_disk' => env('SUNAT_STORAGE_DISK', 'local'),
];
