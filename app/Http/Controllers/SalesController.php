<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Models\Product;
use App\Services\Sunat\SunatSubmissionService;
use App\Services\Sunat\SunatSettingsService;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $documents = BillingDocument::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('series', 'like', "%{$term}%")
                        ->orWhere('number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_document_number', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.index', ['documents' => $documents]);
    }

    public function create(SunatSettingsService $settingsService): View
    {
        return view('sales.create', [
            'products' => Product::query()->where('active', true)->orderBy('name')->get(),
            'settings' => $settingsService->values(),
        ]);
    }

    public function store(Request $request, SunatSettingsService $settingsService): RedirectResponse
    {
        if ((string) $request->input('customer_document_type') === '0' && blank($request->input('customer_name'))) {
            $request->merge(['customer_name' => 'CLIENTES VARIOS']);
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:01,03'],
            'payment_condition' => ['required', 'in:cash,credit'],
            'payment_due_date' => ['nullable', 'required_if:payment_condition,credit', 'date', 'after_or_equal:today'],
            'customer_document_type' => ['required', 'in:0,1,6'],
            'customer_document_number' => ['nullable', 'string', 'max:20'],
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_address' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ]);

        if ($validated['document_type'] === '01' && (
            $validated['customer_document_type'] !== '6'
            || ! preg_match('/^\d{11}$/', (string) $validated['customer_document_number'])
        )) {
            throw ValidationException::withMessages([
                'customer_document_number' => 'Para emitir una factura, el cliente debe tener RUC de 11 dígitos.',
            ]);
        }

        if ($validated['document_type'] === '03' && $validated['customer_document_type'] === '6') {
            throw ValidationException::withMessages([
                'customer_document_type' => 'Las boletas solo permiten DNI o cliente sin documento.',
            ]);
        }

        if ($validated['document_type'] === '03' && $validated['customer_document_type'] === '1'
            && ! preg_match('/^\d{8}$/', (string) $validated['customer_document_number'])) {
            throw ValidationException::withMessages([
                'customer_document_number' => 'Para una boleta con DNI, ingresa los 8 dígitos del documento.',
            ]);
        }

        if ($validated['customer_document_type'] !== '0' && blank($validated['customer_document_number'])) {
            throw ValidationException::withMessages([
                'customer_document_number' => 'Ingresa el número de documento del cliente.',
            ]);
        }

        if ($validated['customer_document_type'] === '0') {
            $validated['customer_document_number'] = null;
        }

        $document = DB::transaction(function () use ($validated, $settingsService): BillingDocument {
            $documentType = $validated['document_type'];
            $settings = $settingsService->values();
            $series = $documentType === '01'
                ? (string) $settings['invoice_series']
                : (string) $settings['receipt_series'];
            $number = ((int) BillingDocument::query()
                ->where('document_type', $documentType)
                ->where('series', $series)
                ->lockForUpdate()
                ->max('number')) + 1;
            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($validated['items'] as $item) {
                $product = Product::query()->where('active', true)->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'Uno de los productos ya no está disponible.']);
                }

                $quantity = (float) $item['quantity'];
                $lineSubtotal = round((float) $product->unit_price * $quantity, 2);
                $lineTax = round($lineSubtotal * ((float) $product->tax_rate / 100), 2);
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
                $lines[] = [
                    'product_id' => $product->id,
                    'code' => $product->code,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_code' => $product->unit_code,
                    'unit_price' => (float) $product->unit_price,
                    'tax_rate' => (float) $product->tax_rate,
                    'subtotal' => $lineSubtotal,
                    'igv' => $lineTax,
                    'total' => round($lineSubtotal + $lineTax, 2),
                ];
            }

            return BillingDocument::create([
                'document_type' => $documentType,
                'series' => $series,
                'number' => $number,
                'issue_date' => now()->toDateString(),
                'currency' => 'PEN',
                'payment_condition' => $validated['payment_condition'],
                'payment_due_date' => $validated['payment_due_date'] ?? null,
                'customer_document_type' => $validated['customer_document_type'],
                'customer_document_number' => $validated['customer_document_number'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_address' => $validated['customer_address'] ?? null,
                'lines' => $lines,
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'total' => round($subtotal + $taxTotal, 2),
                'status' => 'draft',
            ]);
        });

        return redirect()->route('sales.show', $document)->with('status', 'Venta guardada como borrador.');
    }

    public function show(BillingDocument $document): View
    {
        return view('sales.show', ['document' => $document]);
    }

    public function send(BillingDocument $document, SunatSubmissionService $submission): RedirectResponse
    {
        try {
            $result = $submission->send($document);
            $message = ($result['status'] ?? '') === 'accepted'
                ? 'SUNAT aceptó el comprobante.'
                : ($result['message'] ?? 'El comprobante se envió a SUNAT.');

            return redirect()->route('sales.show', $document)->with('status', $message);
        } catch (Throwable $exception) {
            $document->forceFill([
                'status' => 'error',
                'sunat_response_message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'No se pudo completar el envío a SUNAT.',
            ])->save();

            return redirect()->route('sales.show', $document)->withErrors([
                'sunat' => $document->sunat_response_message,
            ]);
        }
    }

    public function consult(BillingDocument $document, SunatSubmissionService $submission): RedirectResponse
    {
        try {
            $result = $submission->consult($document);
            $freshDocument = $document->fresh();
            $message = $result['description'] ?? 'Consulta SUNAT completada.';

            if (request()->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'code' => $result['code'] ?? null,
                    'status' => $freshDocument->status,
                    'status_label' => $freshDocument->status_label,
                    'notes' => $result['notes'] ?? [],
                ]);
            }

            return redirect()->route('sales.show', $document)->with('status', $message);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'No se pudo consultar SUNAT. Intenta nuevamente en unos minutos.';
            $document->forceFill(['sunat_response_message' => $message])->save();

            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('sales.show', $document)->withErrors([
                'sunat' => $message,
            ]);
        }
    }

    public function download(BillingDocument $document, string $artifact)
    {
        $column = match ($artifact) {
            'xml' => 'xml_signed_path',
            'cdr' => 'cdr_path',
            default => abort(404),
        };
        $path = $document->{$column};

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    public function pdf(BillingDocument $document, SunatSettingsService $settingsService)
    {
        $settings = $settingsService->values();
        $descriptionRows = collect($document->lines)->sum(
            fn (array $line): int => max(1, (int) ceil(mb_strlen((string) $line['description']) / 30)),
        );
        $paperHeight = max(430, 360 + ($descriptionRows * 24) + (count($document->lines) * 18));
        $qrBase64 = null;
        $settingsRuc = preg_replace('/\D+/', '', (string) $settings['ruc']);

        if ($document->signature_hash && preg_match('/^\d{11}$/', $settingsRuc)) {
            $customerDocumentType = (string) ($document->customer_document_type ?: '0');
            $customerDocumentNumber = (string) ($document->customer_document_number ?: '0');
            $qrPayload = implode('|', [
                $settingsRuc,
                $document->document_type,
                $document->series,
                $document->number,
                number_format((float) $document->tax_total, 2, '.', ''),
                number_format((float) $document->total, 2, '.', ''),
                $document->issue_date->format('Y-m-d'),
                $customerDocumentType,
                $customerDocumentNumber,
                $document->signature_hash,
            ]);
            $qrCode = new QrCode(
                data: $qrPayload,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
                size: 220,
                margin: 4,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );
            $qrBase64 = base64_encode((new PngWriter())->write($qrCode)->getString());
        }

        return Pdf::loadView('sales.pdf', [
            'document' => $document,
            'companyName' => $settings['business_name'] ?: 'DevFest Piura',
            'companyRuc' => $settings['ruc'],
            'companyAddress' => $settings['address'],
            'qrBase64' => $qrBase64,
        ])->setPaper([0, 0, 78 * 72 / 25.4, $paperHeight])->stream($document->series.'-'.$document->number.'.pdf');
    }
}
