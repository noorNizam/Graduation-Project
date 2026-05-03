<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentUnit extends Model
{
    protected $table = 'payment_units';

    protected $fillable = [
        'name',
    ];
}
