<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SunatSetting extends Model
{
    protected $fillable = [
        'environment',
        'soap_endpoint',
        'ruc',
        'business_name',
        'address',
        'establishment_code',
        'sol_user',
        'sol_password',
        'certificate_path',
        'certificate_password',
        'invoice_series',
        'receipt_series',
        'timeout',
    ];

    protected function casts(): array
    {
        return [
            'sol_password' => 'encrypted',
            'certificate_password' => 'encrypted',
            'timeout' => 'integer',
        ];
    }
}
