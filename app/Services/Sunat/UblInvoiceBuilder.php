<?php

namespace App\Services\Sunat;

use App\Models\BillingDocument;
use RuntimeException;
use Throwable;

class UblInvoiceBuilder
{
    public function __construct(private readonly SunatSettingsService $settings) {}

    public function invoice(BillingDocument $document): string
    {
        if (! in_array($document->document_type, ['01', '03'], true)) {
            throw new RuntimeException('Por ahora solo se emiten facturas y boletas.');
        }

        $settings = $this->settings->values();
        $ruc = trim((string) $settings['ruc']);
        $companyName = trim((string) $settings['business_name']);

        if ($ruc === '' || $companyName === '') {
            throw new RuntimeException('Completa el RUC y la razón social en Configuración SUNAT.');
        }

        return $this->renderInvoiceTemplate([
            'document' => $document,
            'settings' => $settings,
            'ruc' => $ruc,
            'companyName' => $companyName,
        ]);
    }

    private function renderInvoiceTemplate(array $data): string
    {
        $templatePath = app_path('Sunat/Templates/invoice.php');

        if (! is_file($templatePath)) {
            throw new RuntimeException('No se encontró la plantilla XML UBL: '.$templatePath);
        }

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            extract($data, EXTR_SKIP);
            require $templatePath;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }
    }
}
