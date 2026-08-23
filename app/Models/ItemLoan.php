<?php

namespace App\Models;

use Database\Factories\ItemLoanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<ItemLoanFactory>
 */
class ItemLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'borrower_name',
        'borrower_contact',
        'loaned_at',
        'expected_return_at',
        'returned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'loaned_at' => 'date',
            'expected_return_at' => 'date',
            'returned_at' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function statusKey(): string
    {
        if ($this->returned_at !== null) {
            return 'returned';
        }

        if ($this->expected_return_at !== null && $this->expected_return_at->isPast() && ! $this->expected_return_at->isToday()) {
            return 'overdue';
        }

        return 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }
}
