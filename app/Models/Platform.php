<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'status',
        'created_by',
    ];

    /**
     * Scope a query to only include platforms available to a given business.
     * Includes global platforms (business_id = null) and business-specific ones.
     */
    public function scopeAvailableToBusiness($query, $businessId)
    {
        return $query->whereNull('business_id')->orWhere('business_id', $businessId);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
