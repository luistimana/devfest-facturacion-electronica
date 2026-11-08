<?php

namespace App\Services\Sunat;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

class XmlSigner
{
    public function __construct(
        private readonly SunatSettingsService $settings,
        private readonly DemoCertificateService $demoCertificate,
    ) {}

    public function sign(string $xmlContent): array
    {
        [$privateKey, $certificate] = $this->certificateMaterial();
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        if (! $document->loadXML($xmlContent, LIBXML_NONET)) {
            throw new RuntimeException('El XML no es válido y no puede firmarse.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $extensionContent = $xpath->query('/*[local-name()="Invoice"]/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent | /*[local-name()="SummaryDocuments"]/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent')->item(0);

        if (! $extensionContent) {
            throw new RuntimeException('El XML no contiene el espacio UBL reservado para la firma.');
        }

        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::C14N);
        $signature->addReference(
            $document,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($privateKey, false);
        // Insert the signature under UBLExtensions before calculating
        // SignatureValue so canonicalization sees the final namespace context.
        $signature->sign($key, $extensionContent);
        $signature->add509Cert($certificate, true, false, ['issuerSerial' => true]);

        $signedXml = $document->saveXML();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        return [
            'xml' => $signedXml ?: throw new RuntimeException('No se pudo guardar el XML firmado.'),
            'digest' => trim((string) $xpath->evaluate('string(//ds:SignedInfo/ds:Reference/ds:DigestValue)')),
        ];
    }

    private function certificateMaterial(): array
    {
        $settings = $this->settings->values();
        $path = trim((string) $settings['certificate_path']);

        if ($settings['environment'] === 'beta') {
            return $this->demoCertificate->material();
        }

        $fullPath = is_file($path) ? $path : Storage::disk('local')->path($path);

        if ($path === '' || ! is_file($fullPath) || ! is_readable($fullPath)) {
            throw new RuntimeException('Carga un certificado digital válido en Configuración SUNAT.');
        }

        $contents = file_get_contents($fullPath);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('No se pudo leer el certificado configurado.');
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['pfx', 'p12'], true)) {
            $certificates = [];

            if (! openssl_pkcs12_read($contents, $certificates, (string) $settings['certificate_password'])) {
                throw new RuntimeException('No se pudo abrir el certificado PFX/P12; revisa su contraseña.');
            }

            return [(string) $certificates['pkey'], (string) $certificates['cert']];
        }

        $privateKey = openssl_pkey_get_private($contents, (string) $settings['certificate_password']);
        $certificate = openssl_x509_read($contents);
        $privateKeyPem = '';
        $certificatePem = '';

        if (! $privateKey || ! $certificate || ! openssl_pkey_export($privateKey, $privateKeyPem) || ! openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('El PEM debe incluir una clave privada y un certificado X.509 válidos.');
        }

        return [$privateKeyPem, $certificatePem];
    }

}
