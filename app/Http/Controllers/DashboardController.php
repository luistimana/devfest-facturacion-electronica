<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Services\Sunat\SunatSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(SunatSettingsService $settingsService): View
    {
        $documents = BillingDocument::query();
        $settings = $settingsService->values();
        $periodStart = CarbonImmutable::now()->startOfDay()->subDays(29);
        $dailySales = (clone $documents)
            ->whereDate('issue_date', '>=', $periodStart->toDateString())
            ->whereDate('issue_date', '<=', CarbonImmutable::now()->toDateString())
            ->selectRaw('DATE(issue_date) as sale_date, COUNT(*) as sales_count, SUM(total) as sales_total')
            ->groupByRaw('DATE(issue_date)')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->sale_date);
        $salesChart = collect(range(0, 29))->map(function (int $offset) use ($periodStart, $dailySales): array {
            $day = $periodStart->addDays($offset);
            $record = $dailySales->get($day->toDateString());

            return [
                'key' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'count' => (int) ($record?->sales_count ?? 0),
                'total' => (float) ($record?->sales_total ?? 0),
            ];
        });

        return view('dashboard', [
            'documentCount' => (clone $documents)->count(),
            'acceptedCount' => (clone $documents)->where('status', 'accepted')->count(),
            'pendingCount' => (clone $documents)->whereIn('status', ['draft', 'sent', 'processing'])->count(),
            'salesChart' => $salesChart,
            'sunatConfigured' => filled($settings['ruc'])
                && filled($settings['business_name'])
                && filled($settings['soap_endpoint'])
                && ($settings['environment'] === 'beta'
                    || (filled($settings['sol_user']) && filled($settings['sol_password']) && filled($settings['certificate_path']))),
        ]);
    }
}
