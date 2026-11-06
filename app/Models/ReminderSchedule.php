<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The automatic reminder; there is one. It runs at most once per day: at its time or, when the
 * server was off then, as soon as possible later on that same day.
 */
class ReminderSchedule extends Model
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $fillable = [
        'frequency',
        'weekday',
        'monthday',
        'time',
        'pabrik',
        'subject',
        'message',
        'attachments',
        'active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'monthday' => 'integer',
            'attachments' => 'array',
            'active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function isDue(CarbonInterface $now): bool
    {
        $slot = $this->slotOn($now);

        return $this->active && $slot && $now->greaterThanOrEqualTo($slot) && ! $this->ranOn($now);
    }

    /** When it goes out next, or null when it is off. */
    public function nextRunAt(CarbonInterface $now): ?CarbonInterface
    {
        if (! $this->active) {
            return null;
        }

        // the longest wait is a monthly schedule that just ran
        for ($days = 0; $days <= 62; $days++) {
            $day = $now->copy()->addDays($days);
            $slot = $this->slotOn($day);

            if ($slot && ! $this->ranOn($day)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * After saving, a time that already passed today must not fire right away: the first run is
     * the next time that is still ahead.
     */
    public function skipPassedSlot(CarbonInterface $now): void
    {
        $slot = $this->slotOn($now);

        if ($slot && $now->greaterThanOrEqualTo($slot) && ! $this->ranOn($now)) {
            $this->last_run_at = $now;
        }
    }

    /** Take this run; false when another process just did, so it never goes out twice. */
    public function claim(CarbonInterface $now): bool
    {
        $claimed = self::query()
            ->whereKey($this->getKey())
            ->where(fn ($query) => $this->last_run_at
                ? $query->where('last_run_at', $this->last_run_at)
                : $query->whereNull('last_run_at'))
            ->update(['last_run_at' => $now]);

        return $claimed === 1;
    }

    /** The moment on this day in the schedule's timezone, or null when it does not run that day. */
    private function slotOn(CarbonInterface $day): ?CarbonInterface
    {
        $local = $day->copy()->setTimezone(ReminderEmail::TIMEZONE);

        $runs = match ($this->frequency) {
            'weekly' => $local->dayOfWeekIso === $this->weekday,
            'monthly' => $local->day === $this->monthday,
            default => true,
        };

        return $runs ? $local->setTimeFromTimeString($this->time) : null;
    }

    private function ranOn(CarbonInterface $day): bool
    {
        return $this->last_run_at !== null
            && $this->last_run_at->copy()->setTimezone(ReminderEmail::TIMEZONE)->isSameDay($day->copy()->setTimezone(ReminderEmail::TIMEZONE));
    }
}
