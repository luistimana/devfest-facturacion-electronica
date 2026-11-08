<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunat_settings', function (Blueprint $table): void {
            $table->string('establishment_code', 4)->default('0000')->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('sunat_settings', function (Blueprint $table): void {
            $table->dropColumn('establishment_code');
        });
    }
};
