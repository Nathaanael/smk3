<?php

namespace App\Console\Commands;

use App\Models\NonConformity;
use App\Models\ReminderSchedule;
use App\Services\FindingReminders;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Runs every minute (routes/console.php) and sends the automatic reminder once it is due, to
 * everyone who at that moment still has an open finding without a repair photo.
 */
class SendScheduledReminders extends Command
{
    protected $signature = 'reminders:send-scheduled';

    protected $description = 'Send the automatic reminder emails when they are due';

    public function handle(FindingReminders $reminders): int
    {
        $schedule = ReminderSchedule::first();
        $now = now();

        if (! $schedule?->isDue($now) || ! $schedule->claim($now)) {
            return self::SUCCESS;
        }

        $mails = $reminders->mails(
            NonConformity::query()->awaitingRepairPhoto()->when($schedule->pabrik, fn (Builder $query, string $plant) => $query->where('pabrik', $plant)),
            $schedule->subject,
            $schedule->message,
            null,
            $schedule->attachments ?? [],
        );

        $failed = $mails->reject(fn ($mail) => $reminders->deliver($mail, null))->count();

        $this->info(($mails->count() - $failed)." sent, {$failed} failed.");

        return self::SUCCESS;
    }
}
