<?php

namespace App\Services;

use App\Mail\FindingReminderMail;
use App\Models\NonConformity;
use App\Models\ReminderEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns the findings waiting for a repair photo into reminder emails: one per PIC, holding all
 * of that PIC's findings. A PIC without an account has no email address, so gets no email.
 */
class FindingReminders
{
    /**
     * Send one email and record the attempt. A failure is recorded instead of thrown, so it
     * does not stop the next email.
     */
    public function deliver(FindingReminderMail $mail, User $sender): bool
    {
        $error = null;

        try {
            Mail::to($mail->recipient)->send($mail);
        } catch (Throwable $exception) {
            report($exception);
            $error = Str::limit($exception->getMessage(), 1000);
        }

        ReminderEmail::create([
            'sender_id' => $sender->id,
            'recipient_name' => $mail->recipient->name,
            'recipient_email' => $mail->recipient->email,
            'subject' => Str::limit($mail->envelope()->subject, 250),
            'findings_count' => $mail->findings->count(),
            'status' => $error === null ? ReminderEmail::SENT : ReminderEmail::FAILED,
            'error' => $error,
        ]);

        return $error === null;
    }

    /**
     * The people who can be reminded about these findings, by name.
     *
     * @param  Builder<NonConformity>  $pending
     * @return list<array{id: int, name: string, email: string, departments: string, findings: int}>
     */
    public function recipients(Builder $pending): array
    {
        return $this->findingsPerPic($pending)
            ->map(function (Collection $findings) {
                $pic = $findings->first()->picUser;

                return [
                    'id' => $pic->id,
                    'name' => $pic->name,
                    'email' => $pic->email,
                    'departments' => $findings->map(fn (NonConformity $finding) => $finding->departmentName())->unique()->implode(', '),
                    'findings' => $findings->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Only people with findings in $pending can get an email, whatever ids are asked for.
     *
     * @param  Builder<NonConformity>  $pending
     * @param  list<int|string>|null  $only  ids of the chosen people; null means everyone
     * @return Collection<int, FindingReminderMail>
     */
    public function mails(Builder $pending, string $subject, string $message, ?array $only = null): Collection
    {
        $link = route('non-conformities', ['status' => 'open']);
        $groups = $this->findingsPerPic($pending);

        return ($only === null ? $groups : $groups->only($only))
            ->map(fn (Collection $findings) => new FindingReminderMail($findings->first()->picUser, $findings, $subject, $message, $link))
            ->values();
    }

    /**
     * @param  Builder<NonConformity>  $pending
     * @return Collection<int, Collection<int, NonConformity>> keyed by the PIC's user id
     */
    private function findingsPerPic(Builder $pending): Collection
    {
        return $pending->clone()
            ->without('photos')
            ->with('picUser:id,name,email')
            ->oldest('tanggal')
            ->oldest('id')
            ->get()
            ->toBase()
            ->filter(fn (NonConformity $finding) => $finding->picUser instanceof User)
            ->groupBy(fn (NonConformity $finding) => $finding->picUser->id)
            ->map(fn (Collection $findings) => $findings->values());
    }
}
