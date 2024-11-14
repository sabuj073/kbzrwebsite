<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'temp_user_id',
        'card_number',
        'card_type',
        'expiry_month',
        'expiry_year',
        'cvc',
        'billing_address',
    ];

    // Encrypt the card number when saving
 
}
