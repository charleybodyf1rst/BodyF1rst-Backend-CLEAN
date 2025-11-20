<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'stripe_payment_id',
        'stripe_invoice_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'description',
        'metadata',
        'paid_at',
        'failed_at',
        'refunded_at',
        'refund_amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that owns the payment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with the payment
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->status === 'succeeded';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment was refunded
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded' || !is_null($this->refunded_at);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        $currency = strtoupper($this->currency ?? 'USD');
        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get currency symbol
     */
    protected function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];

        return $symbols[$currency] ?? '$';
    }

    /**
     * Scope: Only completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'succeeded']);
    }

    /**
     * Scope: Only pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Only failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Only refunded payments
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded')
            ->orWhereNotNull('refunded_at');
    }

    /**
     * Scope: Payments within date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
