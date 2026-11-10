<?php

namespace App\Console\Commands;

use App\Models\BillingDocument;
use App\Http\Controllers\SalesController;
use App\Services\Sunat\SunatSettingsService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramBotCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Escucha comandos del bot de Telegram para consultas básicas de ventas';

    private bool $awaitingDocumentReference = false;

    public function handle(): int
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            $this->error('Configura TOKEN_TLG en .env antes de iniciar el bot.');
            return self::FAILURE;
        }

        $this->info('Bot de Telegram activo. Presiona Ctrl+C para detenerlo.');
        $offset = 0;

        while (true) {
            try {
                $response = Http::timeout(40)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 30,
                    'allowed_updates' => json_encode(['message', 'callback_query']),
                ]);

                if (! $response->successful() || ! $response->json('ok')) {
                    $this->warn('Telegram no respondió correctamente. Reintentando en 5 segundos.');
                    sleep(5);
                    continue;
                }

                foreach ($response->json('result', []) as $update) {
                    $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
                    $this->handleUpdate($token, $update);
                }
            } catch (ConnectionException) {
                $this->warn('No se pudo conectar con Telegram. Reintentando en 5 segundos.');
                sleep(5);
            }
        }
    }

    private function handleUpdate(string $token, array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleFileRequest($token, $update['callback_query']);
            return;
        }

        $message = $update['message'] ?? null;
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === '' || $text === '') {
            return;
        }

        $allowedChatId = trim((string) config('services.telegram.chat_id'));
        $isAuthorized = $allowedChatId !== '' && hash_equals($allowedChatId, $chatId);

        if (preg_match('/^\/(start|id)(?:@\w+)?$/i', $text)) {
            if ($isAuthorized) {
                $this->sendMessage($token, $chatId, '👋 <b>¡Listo!</b> Elige un comando del menú o envía /cmds.', $this->commandsKeyboard());
            } elseif ($allowedChatId !== '') {
                $this->sendMessage($token, $chatId, 'Este chat no está autorizado.');
            } else {
                $this->sendMessage($token, $chatId, "Para activar el bot, agrega <code>TELEGRAM_CHAT_ID={$chatId}</code> al .env y reinícialo.");
            }
            return;
        }

        if (! $isAuthorized) {
            return;
        }

        if ($this->awaitingDocumentReference && ! str_starts_with($text, '/')) {
            $this->awaitingDocumentReference = false;
            if (preg_match('/^([A-Z]\d{3})-(\d+)$/i', $text, $reference)) {
                $this->sendDocumentStatus($token, $chatId, strtoupper($reference[1]), (int) $reference[2]);
            } else {
                $this->sendMessage($token, $chatId, 'No reconocí ese número. Escríbelo como <b>F001-25</b> o <b>B001-25</b>.');
            }
            return;
        }
        if ($this->awaitingDocumentReference && str_starts_with($text, '/')) {
            $this->awaitingDocumentReference = false;
        }

        if (preg_match('/^\/estado(?:@\w+)?$/i', $text)) {
            $this->awaitingDocumentReference = true;
            $this->sendMessage($token, $chatId, 'Escribe la serie y el número del comprobante.\nEjemplo: <b>F001-25</b>', [
                'force_reply' => true,
                'input_field_placeholder' => 'Ejemplo: F001-25',
            ]);
            return;
        }

        if (preg_match('/^\/(cmds|comandos)(?:@\w+)?$/i', $text)) {
            $this->sendMessage($token, $chatId, "✨ <b>Menú de consultas</b>\n\nElige una opción del teclado para consultar tus ventas.", $this->commandsKeyboard());
            return;
        }

        if (preg_match('/^\/(ventas_hoy|ventas_semana|ventas_mes|ventas)(?:@\w+)?$/i', $text, $matches)) {
            $command = strtolower($matches[1]);
            $start = match ($command) {
                'ventas_semana' => now()->startOfWeek(),
                'ventas_mes' => now()->startOfMonth(),
                default => now()->startOfDay(),
            };
            $label = match ($command) {
                'ventas_semana' => 'esta semana',
                'ventas_mes' => 'este mes',
                default => 'hoy',
            };
            $documents = BillingDocument::query()->whereBetween('issue_date', [$start->toDateString(), now()->toDateString()]);
            $this->sendMessage($token, $chatId, $this->salesSummary($documents, "Ventas {$label}"));
            return;
        }

        if (preg_match('/^\/clientes_mes(?:@\w+)?$/i', $text)) {
            $clients = BillingDocument::query()
                ->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
                ->selectRaw('customer_name, COUNT(*) as document_count, SUM(total) as amount_total')
                ->groupBy('customer_name')
                ->orderByDesc('amount_total')
                ->limit(5)
                ->get();

            $this->sendMessage($token, $chatId, $clients->isEmpty()
                ? 'No hay ventas registradas este mes.'
                : "👥 <b>Principales clientes del mes</b>\n\n".$clients->values()->map(fn ($client, $index) => ($index + 1).'. '.e($client->customer_name).' · <b>S/ '.number_format((float) $client->amount_total, 2, '.', ',').'</b> · '.$client->document_count.' comprobantes')->implode("\n"));
            return;
        }

        if (preg_match('/^\/ultimas_ventas(?:@\w+)?$/i', $text)) {
            $this->sendDocumentList($token, $chatId, 'recent', 0);
            return;
        }

        if (preg_match('/^\/estado\s+([A-Z]\d{3})-(\d+)(?:@\w+)?$/i', $text, $matches)) {
            $this->sendDocumentStatus($token, $chatId, strtoupper($matches[1]), (int) $matches[2]);
            return;
        }

        if (preg_match('/^\/productos(?:@\w+)?$/i', $text)) {
            $documents = BillingDocument::query()->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->get(['lines']);
            $products = [];
            foreach ($documents as $document) {
                foreach ($document->lines ?? [] as $line) {
                    $name = (string) ($line['description'] ?? 'Producto');
                    $products[$name] = ($products[$name] ?? 0) + (float) ($line['quantity'] ?? 0);
                }
            }
            arsort($products);
            $top = array_slice($products, 0, 5, true);
            $this->sendMessage($token, $chatId, $top
                ? "🏆 <b>Productos más vendidos este mes</b> <i>(por unidades)</i>\n\n".collect($top)->map(fn ($quantity, $name) => '• '.e((string) $name).': <b>'.rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.').'</b>')->implode("\n")
                : 'No hay productos vendidos registrados este mes.');
            return;
        }

        $this->sendMessage($token, $chatId, "No reconozco ese comando. Envía <b>/cmds</b> para ver la ayuda.");
    }

    private function salesSummary(Builder $documents, string $label): string
    {
        $count = (clone $documents)->count();
        $total = (float) (clone $documents)->sum('total');

        return "📊 <b>{$label}</b>\n\n📄 Comprobantes: <b>{$count}</b>\n💰 Total: <b>S/ ".number_format($total, 2, '.', ',').'</b>';
    }

    private function sendDocumentStatus(string $token, string $chatId, string $series, int $number): void
    {
        $document = BillingDocument::query()->where('series', $series)->where('number', $number)->first();
        $this->sendMessage($token, $chatId, $document
            ? '🧾 <b>'.e($document->series.'-'.$document->number)."</b>\n\n<b>Estado:</b> ".e($document->status_label)."\n<b>Cliente:</b> ".e($document->customer_name)."\n<b>Total:</b> S/ ".number_format((float) $document->total, 2, '.', ',')."\n<b>Respuesta SUNAT:</b> ".e($document->sunat_response_message ?: 'Sin mensaje adicional.')
            : 'No encontré ese comprobante. Revisa la serie y el número.', $document ? $this->fileKeyboard($document) : null);
    }

    private function sendMessage(string $token, string $chatId, string $text, ?array $keyboard = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = $keyboard;
        }
        Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
    }

    private function handleFileRequest(string $token, array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        $allowedChatId = (string) config('services.telegram.chat_id');
        $data = (string) ($callback['data'] ?? '');

        if ($chatId === '' || $allowedChatId === '' || ! hash_equals($allowedChatId, $chatId)) {
            if ($callbackId !== '') {
                Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackId,
                    'text' => 'Este chat no está autorizado.',
                    'show_alert' => true,
                ]);
            }
            return;
        }

        if (preg_match('/^page:recent:(\d+)$/', $data, $page)) {
            $this->answerCallback($token, $callbackId);
            $this->sendDocumentList($token, $chatId, 'recent', (int) $page[1], $callback['message']['message_id'] ?? null);
            return;
        }

        if (! preg_match('/^file:(pdf|xml):(\d+)$/', $data, $matches)) {
            return;
        }

        $document = BillingDocument::query()->find((int) $matches[2]);
        if (! $document) {
            $this->answerCallback($token, $callbackId, 'No encontré ese comprobante.');
            return;
        }

        try {
            if ($matches[1] === 'xml') {
                $path = $document->xml_signed_path;
                if (! $path || ! Storage::disk('local')->exists($path)) {
                    $this->answerCallback($token, $callbackId, 'El XML firmado aún no está disponible.');
                    return;
                }
                $content = Storage::disk('local')->get($path);
                $filename = basename($path);
                $mimeType = 'application/xml';
            } else {
                $pdfResponse = app(SalesController::class)->pdf($document, app(SunatSettingsService::class));
                $content = $pdfResponse->getContent();
                if (! is_string($content) || ! str_starts_with($content, '%PDF')) {
                    throw new \RuntimeException('No se pudo generar el PDF.');
                }
                $filename = $document->series.'-'.$document->number.'.pdf';
                $mimeType = 'application/pdf';
            }

            $response = Http::timeout(60)
                ->attach('document', $content, $filename, ['Content-Type' => $mimeType])
                ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                    'chat_id' => $chatId,
                    'caption' => '🧾 <b>'.e($document->series.'-'.$document->number).'</b> · '.e($document->customer_name),
                    'parse_mode' => 'HTML',
                ]);
            if (! $response->successful() || ! $response->json('ok')) {
                throw new \RuntimeException('Telegram no aceptó el archivo.');
            }
            $this->answerCallback($token, $callbackId);
        } catch (\Throwable) {
            $this->answerCallback($token, $callbackId, 'No se pudo enviar el archivo. Intenta nuevamente.');
        }
    }

    private function answerCallback(string $token, string $callbackId, ?string $message = null): void
    {
        if ($callbackId === '') {
            return;
        }

        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", array_filter([
            'callback_query_id' => $callbackId,
            'text' => $message,
            'show_alert' => $message !== null,
        ], fn ($value) => $value !== null));
    }

    private function commandsKeyboard(): array
    {
        return ['keyboard' => [
            [['text' => '/ventas_hoy'], ['text' => '/ventas_semana']],
            [['text' => '/ventas_mes'], ['text' => '/ultimas_ventas']],
            [['text' => '/estado'], ['text' => '/clientes_mes']],
            [['text' => '/productos'], ['text' => '/cmds']],
        ], 'resize_keyboard' => true, 'is_persistent' => true];
    }

    private function fileKeyboard(BillingDocument $document): array
    {
        return ['inline_keyboard' => [$this->fileButtons($document)]];
    }

    private function fileButtons(BillingDocument $document): array
    {
        $reference = $document->series.'-'.$document->number;

        return [
            ['text' => '📄 PDF '.$reference, 'callback_data' => 'file:pdf:'.$document->id],
            ['text' => ($document->xml_signed_path ? '📦 XML ' : '⏳ XML ').$reference, 'callback_data' => 'file:xml:'.$document->id],
        ];
    }

    private function sendDocumentList(string $token, string $chatId, string $type, int $offset, mixed $messageId = null): void
    {
        $query = BillingDocument::query();
        $total = (clone $query)->count();
        $pageSize = 5;
        $offset = min(max(0, $offset), max(0, (intdiv(max(0, $total - 1), $pageSize) * $pageSize)));
        $documents = $query->latest('issue_date')->latest('id')->skip($offset)->take($pageSize)->get();

        if ($documents->isEmpty()) {
            $this->sendMessage($token, $chatId, 'Todavía no hay comprobantes registrados.');
            return;
        }

        $lines = $documents->map(fn (BillingDocument $document) => sprintf(
            '<b>%s-%s</b> · %s · <b>S/ %s</b> · %s',
            e($document->series),
            e((string) $document->number),
            e($document->customer_name),
            number_format((float) $document->total, 2, '.', ','),
            e($document->status_label),
        ));
        $keyboard = $documents->map(fn (BillingDocument $document) => $this->fileButtons($document))->all();
        $navigation = [];
        if ($offset > 0) {
            $navigation[] = ['text' => '⬅️ Anterior', 'callback_data' => 'page:'.$type.':'.max(0, $offset - $pageSize)];
        }
        if ($offset + $pageSize < $total) {
            $navigation[] = ['text' => 'Siguiente ➡️', 'callback_data' => 'page:'.$type.':'.($offset + $pageSize)];
        }
        if ($navigation !== []) {
            $keyboard[] = $navigation;
        }

        $label = 'Comprobantes';
        $range = ($offset + 1).'–'.($offset + $documents->count())." de {$total}";
        $text = '🧾 <b>'.$label."</b>\n<i>Mostrando {$range}</i>\n\n".$lines->implode("\n")."\n\nToca PDF o XML para recibir el archivo.";

        if ($messageId !== null) {
            Http::timeout(15)->post("https://api.telegram.org/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => $keyboard],
            ]);
            return;
        }

        $this->sendMessage($token, $chatId, $text, ['inline_keyboard' => $keyboard]);
    }
}
