<?php

namespace App\Traits;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBusiness
{
    /**
     * Boot the trait to apply global scope and model events.
     */
    protected static function bootBelongsToBusiness(): void
    {
        // Global scope to filter by current business_id
        static::addGlobalScope('business', function (Builder $builder) {
            $businessId = static::getCurrentBusinessId();
            if ($businessId) {
                $builder->where(static::getBusinessIdColumnName(), $businessId);
            }
        });

        // Automatically set business_id on creation if not already set
        static::creating(function ($model) {
            if (!$model->business_id) {
                $model->business_id = static::getCurrentBusinessId();
            }
        });
    }

    /**
     * Get the current active business ID.
     */
    public static function getCurrentBusinessId(): ?int
    {
        if (session()->has('active_business_id')) {
            return (int) session('active_business_id');
        }

        if (auth()->check() && auth()->user()->current_business_id) {
            return (int) auth()->user()->current_business_id;
        }

        return null;
    }

    /**
     * Get the column name used for scoping.
     */
    protected static function getBusinessIdColumnName(): string
    {
        return 'business_id';
    }

    /**
     * Get the business that owns this model.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
