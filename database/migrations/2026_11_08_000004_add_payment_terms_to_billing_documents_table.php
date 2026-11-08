<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_documents', function (Blueprint $table): void {
            $table->string('payment_condition', 10)->default('cash')->after('currency');
            $table->date('payment_due_date')->nullable()->after('payment_condition');
        });
    }

    public function down(): void
    {
        Schema::table('billing_documents', function (Blueprint $table): void {
            $table->dropColumn(['payment_condition', 'payment_due_date']);
        });
    }
};
