<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'client_id',
        'quote_number',
        'title',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'notes',
        'valid_until',
        'accepted_at',
        'signature_name',
        'signature_date',
    ];

    /**
     * Get the client that owns the quote.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the line items for the quote.
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }
}
