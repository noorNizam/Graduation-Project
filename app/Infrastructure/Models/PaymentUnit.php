<?php

namespace App\Infrastructure\Models;

use Database\Factories\PaymentUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentUnit extends Model
{
    use HasFactory;

    protected static function newFactory(): PaymentUnitFactory
    {
        return PaymentUnitFactory::new();
    }

    protected $table = 'payment_units';

    protected $fillable = [
        'name',
    ];
}
