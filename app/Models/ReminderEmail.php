<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to send a reminder email to one person. `redirected_to` is only set on trial sends
 * from before going live, which went to that address instead of the recipient.
 */
class ReminderEmail extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** The history is shown and filtered in this timezone; the database stores UTC. */
    public const TIMEZONE = 'Asia/Jakarta';

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

    /**
     * @param  array{q?: ?string, sender?: int|string|null, dari?: ?string, sampai?: ?string}  $filters
     */
    public function scopeMatching(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('recipient_name', 'like', "%{$search}%")
                ->orWhere('recipient_email', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->when($filters['sender'] ?? null, fn (Builder $query, $sender) => $query->where('sender_id', $sender))
            ->when($filters['dari'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from, self::TIMEZONE)->startOfDay()->utc()))
            ->when($filters['sampai'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to, self::TIMEZONE)->endOfDay()->utc()));
    }
}
