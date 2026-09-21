<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRecipient extends Model
{
    protected $fillable = ['vendor_id', 'recipient_code'];
}
