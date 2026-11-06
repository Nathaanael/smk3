<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindingFilterRequest;
use App\Http\Requests\ReminderMessageRequest;
use App\Mail\FindingReminderMail;
use App\Models\NonConformity;
use App\Models\ReminderEmail;
use App\Models\User;
use App\Services\FindingReminders;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
            'sentEmails' => $this->historyPage(ReminderEmail::SENT),
            'failedEmails' => $this->historyPage(ReminderEmail::FAILED),
            'senders' => $this->senders(),
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
     * Send one email to each chosen PIC of the filtered list. One failing address does not stop the
     * others. The page calls this once per person, to show the progress in a loading bar.
     */
    public function send(ReminderMessageRequest $request, FindingReminders $reminders): JsonResponse
    {
        $mails = $this->mails($request, $reminders);
        $failed = $mails->reject(fn (FindingReminderMail $mail) => $reminders->deliver($mail, $request->user()))->count();

        return response()->json(['sent' => $mails->count() - $failed, 'failed' => $failed]);
    }

    /**
     * The results of one history tab, for its search, filters and page links.
     */
    public function history(Request $request, string $status): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sender' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page' => ['nullable', 'integer', Rule::in(FindingFilterRequest::PER_PAGE_OPTIONS)],
        ]);

        return view('pages.emailReminders.history', [
            'emails' => $this->historyPage($status, $filters),
            'status' => $status,
            'filtered' => collect($filters)->except('per_page')->filter()->isNotEmpty(),
        ]);
    }

    /**
     * One page of a history tab, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReminderEmail>
     */
    private function historyPage(string $status, array $filters = []): LengthAwarePaginator
    {
        return ReminderEmail::query()
            ->where('status', $status)
            ->matching($filters)
            ->with('sender:id,name')
            ->latest('id')
            ->paginate($filters['per_page'] ?? self::PER_PAGE)
            ->withPath(route('email-reminders.history', $status))
            ->appends(array_filter($filters));
    }

    /**
     * Who sent something so far, for the sender filter of the history.
     *
     * @return array<int, string>
     */
    private function senders(): array
    {
        return User::query()
            ->whereIn('id', ReminderEmail::query()->select('sender_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
