<?php

namespace App\Services\Sunat;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoCertificateService
{
    private const PATH = 'sunat/certificates/beta-demo.pem';

    public function isPrepared(): bool
    {
        return Storage::disk('local')->exists(self::PATH);
    }

    public function prepare(): string
    {
        if ($this->isPrepared()) {
            return self::PATH;
        }

        $opensslConfig = $this->opensslConfigPath();
        $opensslOptions = $opensslConfig !== null ? ['config' => $opensslConfig] : [];
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
            ...$opensslOptions,
        ]);

        if ($privateKey === false) {
            throw new RuntimeException('No se pudo crear el certificado PEM de pruebas.');
        }

        $request = openssl_csr_new([
            'commonName' => 'SUNAT BETA - certificado temporal',
            'organizationName' => 'DevFest Piura - solo pruebas',
        ], $privateKey, ['digest_alg' => 'sha256', ...$opensslOptions]);
        $certificate = $request ? openssl_csr_sign($request, null, $privateKey, 3650, ['digest_alg' => 'sha256', ...$opensslOptions]) : false;
        $privateKeyPem = '';
        $certificatePem = '';

        if ($certificate === false || ! openssl_pkey_export($privateKey, $privateKeyPem) || ! openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('No se pudo preparar el certificado PEM de pruebas.');
        }

        if (! Storage::disk('local')->put(self::PATH, $privateKeyPem.$certificatePem)) {
            throw new RuntimeException('No se pudo guardar el certificado PEM en almacenamiento privado.');
        }

        return self::PATH;
    }

    public function material(): array
    {
        $path = $this->prepare();
        $contents = Storage::disk('local')->get($path);
        $privateKey = openssl_pkey_get_private($contents);
        $certificate = openssl_x509_read($contents);
        $certificatePem = '';

        if (! $privateKey || ! $certificate || ! openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('El certificado PEM de pruebas guardado no es válido.');
        }

        // The stored PEM already contains the private key. Exporting it again
        // requires an OpenSSL config on some Laragon PHP setups; pass the
        // original PEM to XMLSecLibs, which reads the key directly.
        return [$contents, $certificatePem];
    }

    private function opensslConfigPath(): ?string
    {
        $environmentPath = getenv('OPENSSL_CONF');
        $candidates = array_filter([
            $environmentPath !== false ? $environmentPath : null,
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
