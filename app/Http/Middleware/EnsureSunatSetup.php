<?php

namespace App\Http\Middleware;

use App\Models\SunatSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSunatSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = SunatSetting::query()->first();

        if (blank($setting?->ruc) || blank($setting?->business_name)) {
            return redirect()->route('settings.sunat.edit')->with('onboarding', true);
        }

        return $next($request);
    }
}
