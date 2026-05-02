<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletModel extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'title',
        'balance',
        'unit_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The payment unit for the wallet.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PaymentUnit::class, 'unit_id');
    }
}
