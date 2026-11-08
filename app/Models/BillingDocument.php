<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingDocument extends Model
{
    protected $fillable = [
        'document_type',
        'series',
        'number',
        'issue_date',
        'currency',
        'payment_condition',
        'payment_due_date',
        'customer_document_type',
        'customer_document_number',
        'customer_name',
        'customer_address',
        'lines',
        'subtotal',
        'tax_total',
        'total',
        'status',
        'filename',
        'xml_unsigned_path',
        'xml_signed_path',
        'pdf_path',
        'cdr_path',
        'ticket',
        'signature_hash',
        'sunat_response_code',
        'sunat_response_message',
        'sunat_response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'payment_due_date' => 'date',
            'lines' => 'array',
            'sunat_response' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Borrador',
            'sent' => 'Enviado',
            'processing' => 'En proceso',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'error' => 'Error',
            default => 'Sin estado',
        };
    }
}
