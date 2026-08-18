<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retainer extends Model
{
    use HasFactory, BelongsToBusiness, SoftDeletes;

    protected $fillable = [
        'business_id',
        'client_id',
        'name',
        'description',
        'amount',
        'billing_cycle',
        'status',
        'start_date',
        'end_date',
        'renewal_date',
        'allocated_hours',
        'assigned_manager',
        'terms',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'allocated_hours' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
