<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'sender_type',
        'sender_id',
        'receiver_type',
        'receiver_id',
        'content',
        'read_at',
        'attachment_path',
        'attachment_name',
        'reply_to_id',
        'deleted_by_sender',
        'deleted_by_receiver',
        'is_pinned',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the sender of the message.
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the receiver of the message.
     */
    public function receiver(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the message this message is replying to, if any.
     */
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }
}
