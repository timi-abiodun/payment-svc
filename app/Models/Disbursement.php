<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $fillable = [
        'vendor_id', 'booking_id', 'tranche', 'amount_kobo',
        'status', 'reference', 'provider_ref', 'meta',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'meta' => 'array',
    ];
}
