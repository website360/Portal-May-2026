<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    protected $fillable = ['ticket_message_id', 'path', 'name', 'size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
