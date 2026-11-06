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
    protected $signature = 'reminders:send-scheduled {--now : Run right away, whatever its time and whether it is on, to try it out}';

    protected $description = 'Send the automatic reminder emails when they are due';

    public function handle(FindingReminders $reminders): int
    {
        $schedule = ReminderSchedule::first();
        $now = now();

        if (! $schedule) {
            if ($this->option('now')) {
                $this->warn('No schedule has been saved yet.');
            }

            return self::SUCCESS;
        }

        if ((! $this->option('now') && ! $schedule->isDue($now)) || ! $schedule->claim($now)) {
            return self::SUCCESS;
        }

        // only "log" unless changed on purpose in .env (REMINDER_SCHEDULE_MAILER)
        $mailer = config('smk3.reminder_schedule_mailer');

        $mails = $reminders->mails(
            NonConformity::query()->awaitingRepairPhoto()->when($schedule->pabrik, fn (Builder $query, string $plant) => $query->where('pabrik', $plant)),
            $schedule->subject,
            $schedule->message,
            null,
            $schedule->attachments ?? [],
        );

        $failed = $mails->reject(fn ($mail) => $reminders->deliver($mail, null, $mailer))->count();

        $this->info("Mailer {$mailer}: ".($mails->count() - $failed)." sent, {$failed} failed.");

        return self::SUCCESS;
    }
}
