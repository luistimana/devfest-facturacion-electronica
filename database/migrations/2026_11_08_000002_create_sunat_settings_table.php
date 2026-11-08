<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunat_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 20)->default('beta');
            $table->string('soap_endpoint')->nullable();
            $table->string('ruc', 11)->nullable();
            $table->string('business_name', 200)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('sol_user', 80)->nullable();
            $table->text('sol_password')->nullable();
            $table->string('certificate_path')->nullable();
            $table->text('certificate_password')->nullable();
            $table->string('invoice_series', 4)->default('F001');
            $table->string('receipt_series', 4)->default('B001');
            $table->unsignedSmallInteger('timeout')->default(40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_settings');
    }
};
