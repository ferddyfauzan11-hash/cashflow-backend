<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pocket extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
        'target_amount',
        'current_balance',
        'has_auto_target',
        'target_months',
        'order_index',
        'is_deleted',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'has_auto_target' => 'boolean',
        'is_deleted' => 'boolean',
        'target_months' => 'integer',
        'order_index' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function allocationsFrom(): HasMany
    {
        return $this->hasMany(PocketAllocation::class, 'from_pocket_id');
    }

    public function allocationsTo(): HasMany
    {
        return $this->hasMany(PocketAllocation::class, 'to_pocket_id');
    }
}
