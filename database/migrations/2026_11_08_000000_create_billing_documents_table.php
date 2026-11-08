<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 2);
            $table->string('series', 4);
            $table->unsignedBigInteger('number');
            $table->date('issue_date');
            $table->string('currency', 3)->default('PEN');
            $table->string('customer_document_type', 1)->nullable();
            $table->string('customer_document_number', 20)->nullable();
            $table->string('customer_name', 200);
            $table->string('customer_address', 300)->nullable();
            $table->json('lines');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_total', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('status', 30)->default('draft');
            $table->string('filename')->nullable();
            $table->string('xml_unsigned_path')->nullable();
            $table->string('xml_signed_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('ticket')->nullable();
            $table->string('signature_hash', 100)->nullable();
            $table->string('sunat_response_code', 20)->nullable();
            $table->text('sunat_response_message')->nullable();
            $table->json('sunat_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'series', 'number']);
            $table->index(['document_type', 'issue_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documents');
    }
};
