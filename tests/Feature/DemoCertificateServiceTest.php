<?php

namespace Tests\Feature;

use App\Services\Sunat\DemoCertificateService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCertificateServiceTest extends TestCase
{
    public function test_it_prepares_a_usable_demo_certificate(): void
    {
        Storage::fake('local');

        $service = app(DemoCertificateService::class);
        $path = $service->prepare();
        [$privateKey, $certificate] = $service->material();

        Storage::disk('local')->assertExists($path);
        $this->assertTrue(openssl_x509_check_private_key($certificate, $privateKey));
        $this->assertSame($path, $service->prepare());
    }
}
