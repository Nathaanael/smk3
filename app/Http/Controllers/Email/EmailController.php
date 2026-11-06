<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindingFilterRequest;
use App\Models\NonConformity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Email reminder page: the findings still waiting for a repair photo, longest waiting first.
     * Uses the search and filters of the findings list.
     */
    public function index(FindingFilterRequest $request): View|string|RedirectResponse
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
            'recipients' => $pending->clone()->has('picUser')->distinct()->count('pic'),
            'filters' => $filters,
            'filtered' => collect($filters)->only(['q', 'pabrik', 'departemen', 'kategori', 'dari', 'sampai'])->filter()->isNotEmpty(),
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'categories' => config('smk3.categories'),
            'departments' => collect($plants)->mapWithKeys(fn (string $plant) => [$plant => NonConformity::departmentsFor($plant)])->all(),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }
}
