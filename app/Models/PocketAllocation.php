<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PocketAllocation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'from_pocket_id',
        'to_pocket_id',
        'amount',
        'note',
        'allocation_date',
        'is_deleted',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
        'is_deleted' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromPocket(): BelongsTo
    {
        return $this->belongsTo(Pocket::class, 'from_pocket_id');
    }

    public function toPocket(): BelongsTo
    {
        return $this->belongsTo(Pocket::class, 'to_pocket_id');
    }
}
