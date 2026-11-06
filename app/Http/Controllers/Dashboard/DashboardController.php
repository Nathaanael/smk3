<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Greeting and summary charts of one plant over a period; without dates it covers all data.
     */
    public function index(Request $request, DashboardSummary $summary): View|string
    {
        $user = $request->user();
        $plants = $user->accessiblePlants();

        $validated = $request->validate([
            'pabrik' => ['nullable', Rule::in($plants)],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $filters = [
            'pabrik' => $validated['pabrik'] ?? $plants[0] ?? null,
            'dari' => $validated['dari'] ?? null,
            'sampai' => $validated['sampai'] ?? null,
        ];

        $view = view('pages.dashboard.index', [
            'title' => 'Dashboard',
            'summary' => $filters['pabrik'] ? $summary->build($user, $filters['pabrik'], $filters['dari'], $filters['sampai']) : null,
            'filters' => $filters,
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'currentMonth' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        ]);

        // AJAX filter changes only need the charts
        return $request->header('X-Partial') === 'results' ? $view->fragment('results') : $view;
    }
}
