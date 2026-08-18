<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'tax',
        'total',
    ];

    /**
     * Get the quote that owns the item.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}
