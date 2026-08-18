<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Client extends Authenticatable implements HasMedia
{
    use HasFactory, BelongsToBusiness, InteractsWithMedia;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'tax_number',
        'status',
        'currency',
    ];

    /**
     * Projects associated with this client.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Quotes associated with this client.
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Invoices associated with this client.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function retainers()
    {
        return $this->hasMany(Retainer::class);
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
