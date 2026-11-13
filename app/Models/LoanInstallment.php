<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'loan_id',
        'user_id',
        'amount_paid',
        'overpayment_amount',
        'payment_date',
        'auto_income_generated',
        'notes',
        'is_deleted',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'overpayment_amount' => 'decimal:2',
        'payment_date' => 'date',
        'auto_income_generated' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
