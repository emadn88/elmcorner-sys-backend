<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Expense extends Model
{
    protected $fillable = [
        'category',
        'description',
        'amount',
        'currency',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Get the user who created the expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Format amount with currency symbol.
     */
    public function getFormattedAmountAttribute(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'EGP' => 'E£',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency . ' ';
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if ($category && $category !== 'all') {
            return $query->where('category', $category);
        }
        return $query;
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('expense_date', '>=', $from);
        }
        if ($to) {
            $query->where('expense_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope a query to filter by currency.
     */
    public function scopeByCurrency(Builder $query, ?string $currency): Builder
    {
        if ($currency) {
            return $query->where('currency', $currency);
        }
        return $query;
    }
}
