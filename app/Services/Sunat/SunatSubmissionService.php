<?php

namespace App\Services\Sunat;

use App\Models\BillingDocument;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class SunatSubmissionService
{
    public function __construct(
        private readonly UblInvoiceBuilder $builder,
        private readonly XmlSigner $signer,
        private readonly CdrProcessor $cdrProcessor,
        private readonly SunatSettingsService $settingsService,
    ) {}

    public function send(BillingDocument $document): array
    {
        $settings = $this->settingsService->values();
        $this->assertConfiguration($settings);
        $xmlContent = $this->builder->invoice($document);
        $signed = $this->signer->sign($xmlContent);
        $filename = $this->filename($document, $settings);
        $folder = 'sunat/'.($document->document_type === '03' ? 'boletas' : 'facturas').'/'.$document->issue_date->format('Y/m');
        $unsignedPath = $folder.'/'.$filename.'.unsigned.xml';
        $signedPath = $folder.'/'.$filename.'.xml';
        Storage::disk('local')->put($unsignedPath, $xmlContent);
        Storage::disk('local')->put($signedPath, $signed['xml']);
        $zipContent = $this->zip($filename.'.xml', $signed['xml']);
        $zipPath = $folder.'/'.$filename.'.zip';
        Storage::disk('local')->put($zipPath, $zipContent);
        $document->forceFill([
            'filename' => $filename,
            'xml_unsigned_path' => $unsignedPath,
            'xml_signed_path' => $signedPath,
            'signature_hash' => $signed['digest'],
            'status' => 'sending',
        ])->save();

        $response = $this->soap('sendBill', [
            'fileName' => $filename.'.zip',
            'contentFile' => base64_encode($zipContent),
        ], $settings);
        $fault = $this->soapFault($response);

        if ($fault !== '') {
            throw new RuntimeException($fault);
        }

        $cdr = $this->value($response, 'applicationResponse') ?: $this->value($response, 'document');

        if ($cdr === '') {
            throw new RuntimeException('SUNAT respondió, pero no incluyó el archivo CDR.');
        }

        $result = $this->cdrProcessor->store($document, $cdr);
        $document->forceFill(['sent_at' => now()])->save();

        return ['status' => $document->fresh()->status, ...$result];
    }

    public function consult(BillingDocument $document): array
    {
        $settings = $this->settingsService->values();
        $this->assertConfiguration($settings);

        if (! $document->ticket) {
            throw new RuntimeException('El comprobante no tiene ticket SUNAT para consultar.');
        }

        $response = $this->soap('getStatus', ['ticket' => $document->ticket], $settings, min(10, (int) $settings['timeout']));
        $fault = $this->soapFault($response);

        if ($fault !== '') {
            throw new RuntimeException($fault);
        }

        $statusCode = $this->value($response, 'statusCode');
        $cdr = $this->value($response, 'content');

        if ($cdr !== '') {
            return $this->cdrProcessor->store($document, $cdr);
        }

        $message = match ($statusCode) {
            '98' => 'El resumen continúa en proceso en SUNAT.',
            '99' => 'SUNAT informó un error al procesar el resumen.',
            default => 'SUNAT aún no devolvió una CDR. Código: '.$statusCode,
        };
        $document->forceFill([
            'status' => $statusCode === '98' ? 'processing' : ($statusCode === '99' ? 'rejected' : $document->status),
            'sunat_response_code' => $statusCode,
            'sunat_response_message' => $message,
        ])->save();

        return ['status' => $document->fresh()->status, 'code' => $statusCode, 'description' => $message];
    }

    private function soap(string $operation, array $fields, array $settings, ?int $timeout = null): string
    {
        $username = htmlspecialchars((string) $settings['ruc'].(string) $settings['sol_user'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $password = htmlspecialchars((string) $settings['sol_password'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '<'.$name.'>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$name.'>';
        }

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">'
            .'<soapenv:Header><wsse:Security><wsse:UsernameToken><wsse:Username>'.$username.'</wsse:Username><wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password></wsse:UsernameToken></wsse:Security></soapenv:Header>'
            .'<soapenv:Body><ser:'.$operation.'>'.$body.'</ser:'.$operation.'></soapenv:Body></soapenv:Envelope>';

        $timeout ??= (int) $settings['timeout'];
        $response = Http::withHeaders([
            'Accept' => 'text/xml',
            'SOAPAction' => '"urn:'.$operation.'"',
        ])->withBody($envelope, 'text/xml; charset=utf-8')
            ->connectTimeout(min(10, $timeout))
            ->timeout($timeout)
            ->send('POST', (string) $settings['soap_endpoint']);

        if (! $response->successful()) {
            throw new RuntimeException('SUNAT respondió HTTP '.$response->status().'. '.$response->body());
        }

        return $response->body();
    }

    private function value(string $xml, string $localName): string
    {
        $document = new DOMDocument();
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('SUNAT devolvió una respuesta SOAP inválida.');
        }
        $xpath = new DOMXPath($document);

        return trim((string) $xpath->evaluate('string(//*[local-name()="'.$localName.'"][1])'));
    }

    private function soapFault(string $xml): string
    {
        $document = new DOMDocument();
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            return 'SUNAT devolvió una respuesta SOAP inválida.';
        }
        $xpath = new DOMXPath($document);

        return trim((string) $xpath->evaluate('string(//*[local-name()="Fault"]/*[local-name()="faultstring"][1])'));
    }

    private function zip(string $filename, string $xml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sunat_');
        if ($path === false) {
            throw new RuntimeException('No se pudo preparar el archivo ZIP para SUNAT.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true || ! $zip->addFromString($filename, $xml) || ! $zip->close()) {
                throw new RuntimeException('No se pudo crear el ZIP para SUNAT.');
            }

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function filename(BillingDocument $document, array $settings): string
    {
        return $settings['ruc'].'-'.$document->document_type.'-'.$document->series.'-'.str_pad((string) $document->number, 8, '0', STR_PAD_LEFT);
    }

    private function assertConfiguration(array $settings): void
    {
        $required = [
            'ruc' => 'SUNAT_RUC (RUC del emisor, de tu empresa)',
            'business_name' => 'SUNAT_BUSINESS_NAME',
            'sol_user' => 'SUNAT_SOL_USER',
            'sol_password' => 'Clave SOL',
            'soap_endpoint' => 'Endpoint SOAP',
        ];
        $missing = [];

        foreach ($required as $key => $environmentVariable) {
            if (blank($settings[$key] ?? null)) {
                $missing[] = $environmentVariable;
            }
        }

        if (($settings['environment'] ?? 'beta') === 'production' && blank($settings['certificate_path'] ?? null)) {
            $missing[] = 'certificado digital del emisor';
        }

        if ($missing !== []) {
            throw new RuntimeException('Completa estos datos en Configuración SUNAT: '.implode(', ', $missing).'.');
        }
    }
}
