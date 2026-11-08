<?php

namespace App\Services\Sunat;

use App\Models\BillingDocument;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class CdrProcessor
{
    public function store(BillingDocument $document, string $base64Zip): array
    {
        $zipBytes = base64_decode($base64Zip, true);

        if (! is_string($zipBytes) || $zipBytes === '') {
            throw new RuntimeException('SUNAT devolvió una respuesta CDR vacía o ilegible.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'cdr_');

        if ($tempPath === false || file_put_contents($tempPath, $zipBytes) === false) {
            throw new RuntimeException('No se pudo preparar el ZIP de respuesta de SUNAT.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($tempPath) !== true) {
                throw new RuntimeException('La respuesta de SUNAT no contiene un ZIP CDR válido.');
            }

            $xmlContent = null;
            $cdrName = null;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (is_string($name) && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xml' && ! str_contains($name, '..')) {
                    $xmlContent = $zip->getFromIndex($index);
                    $cdrName = basename($name);
                    break;
                }
            }

            $zip->close();

            if (! is_string($xmlContent) || $xmlContent === '') {
                throw new RuntimeException('No se encontró el XML de la CDR dentro del ZIP.');
            }
        } finally {
            @unlink($tempPath);
        }

        $cdrXml = new DOMDocument();

        if (! $cdrXml->loadXML($xmlContent, LIBXML_NONET)) {
            throw new RuntimeException('El XML CDR recibido no se pudo interpretar.');
        }

        $xpath = new DOMXPath($cdrXml);
        $code = trim((string) $xpath->evaluate('string(//*[local-name()="ResponseCode"][1])'));
        $description = trim((string) $xpath->evaluate('string(//*[local-name()="Description"][1])'));
        $notes = [];

        foreach ($xpath->query('//*[local-name()="Note"]') as $note) {
            $value = trim($note->textContent);
            if ($value !== '') {
                $notes[] = $value;
            }
        }

        $folder = 'sunat/cdr/'.$document->issue_date->format('Y/m');
        $xmlPath = $folder.'/'.$cdrName;
        $zipPath = $folder.'/R-'.$document->filename.'.zip';
        Storage::disk('local')->put($xmlPath, $xmlContent);
        Storage::disk('local')->put($zipPath, $zipBytes);

        $accepted = $code === '0';
        $document->forceFill([
            'cdr_path' => $zipPath,
            'sunat_response_code' => $code,
            'sunat_response_message' => $description,
            'sunat_response' => ['notes' => $notes, 'cdr_xml_path' => $xmlPath],
            'status' => $accepted ? 'accepted' : 'rejected',
        ])->save();

        return ['accepted' => $accepted, 'code' => $code, 'description' => $description, 'notes' => $notes];
    }
}
