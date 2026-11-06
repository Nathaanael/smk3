<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindingFilterRequest;
use App\Http\Requests\ReminderMessageRequest;
use App\Mail\FindingReminderMail;
use App\Models\NonConformity;
use App\Services\FindingReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class EmailController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Email reminder page: the findings still waiting for a repair photo, longest waiting first.
     * Uses the search and filters of the findings list.
     */
    public function index(FindingFilterRequest $request, FindingReminders $reminders): View|string|RedirectResponse
    {
        $plants = $request->user()->accessiblePlants();
        $filters = $request->validated();

        $pending = $request->findings()->awaitingRepairPhoto()->reorder();

        $findings = $pending->clone()
            ->oldest('tanggal')
            ->oldest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // back on a remembered page that no longer exists (repair photos were added in the meantime)
        if ($findings->isEmpty() && $findings->currentPage() > 1 && ! $request->hasHeader('X-Partial')) {
            return redirect()->route('email-reminders', ['page' => $findings->lastPage()] + $request->query());
        }

        // AJAX search/filter/pagination only needs the results part of the page
        return view('pages.emailReminders.index', [
            'title' => __('reminder.title'),
            'findings' => $findings,
            // only PICs with an account have an email address to send to
            'recipients' => $reminders->recipients($pending),
            'filters' => $filters,
            'filtered' => collect($filters)->only(['q', 'pabrik', 'departemen', 'kategori', 'dari', 'sampai'])->filter()->isNotEmpty(),
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'categories' => config('smk3.categories'),
            'departments' => collect($plants)->mapWithKeys(fn (string $plant) => [$plant => NonConformity::departmentsFor($plant)])->all(),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }

    /**
     * What the first recipient would get, as an example. Nothing is sent.
     */
    public function preview(ReminderMessageRequest $request, FindingReminders $reminders): View|RedirectResponse
    {
        $mails = $this->mails($request, $reminders);
        $mail = $mails->first();

        if (! $mail) {
            return redirect()->route('email-reminders', $request->query())->with('error', __('reminder.send.none'));
        }

        return view('pages.emailReminders.preview', [
            'title' => __('reminder.preview.title'),
            'recipient' => $mail->recipient,
            'subject' => $mail->envelope()->subject,
            'html' => $mail->render(),
            'recipients' => $mails->count(),
        ]);
    }

    /**
     * Send one email to each chosen PIC of the filtered list. One failing address does not stop the others.
     */
    public function send(ReminderMessageRequest $request, FindingReminders $reminders): RedirectResponse
    {
        $mails = $this->mails($request, $reminders);
        $back = redirect()->route('email-reminders', $request->query());

        if ($mails->isEmpty()) {
            return $back->with('error', __('reminder.send.none'));
        }

        $failed = 0;
        foreach ($mails as $mail) {
            try {
                Mail::to($mail->recipient)->send($mail);
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return $failed === 0
            ? $back->with('success', __('reminder.send.sent', ['sent' => $mails->count()]))
            : $back->with('error', __('reminder.send.partial', ['sent' => $mails->count() - $failed, 'failed' => $failed]));
    }

    /**
     * @return Collection<int, FindingReminderMail>
     */
    private function mails(ReminderMessageRequest $request, FindingReminders $reminders): Collection
    {
        return $reminders->mails(
            $request->findings()->awaitingRepairPhoto()->reorder(),
            $request->validated('subject'),
            $request->validated('message'),
            $request->validated('recipients'),
        );
    }
}
