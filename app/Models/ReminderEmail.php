<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to send a reminder email to one person.
 */
class ReminderEmail extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'sender_id',
        'recipient_name',
        'recipient_email',
        'redirected_to',
        'subject',
        'findings_count',
        'status',
        'error',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
