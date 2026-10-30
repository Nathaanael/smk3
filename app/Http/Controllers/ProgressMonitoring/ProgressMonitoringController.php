<?php

namespace App\Http\Controllers\ProgressMonitoring;

use App\Http\Controllers\Controller;
use App\Services\ProgressMonitor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgressMonitoringController extends Controller
{
    /**
     * Recap per department of one plant over a period; without dates it shows the current month
     * (as the legacy dashboard did).
     */
    public function index(Request $request, ProgressMonitor $monitor): View|string
    {
        $plants = $request->user()->accessiblePlants();

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
        if (! $request->hasAny(['dari', 'sampai'])) {
            $filters['dari'] = now()->startOfMonth()->toDateString();
            $filters['sampai'] = now()->endOfMonth()->toDateString();
        }

        $recap = $filters['pabrik']
            ? $monitor->recap($filters['pabrik'], $filters['dari'], $filters['sampai'])
            : ['rows' => [], 'total' => null, 'criteria' => 0];

        $view = view('pages.progressMonitoring.index', [
            'title' => __('smk3.progressMonitoring'),
            'recap' => $recap,
            'filters' => $filters,
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'currentMonth' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        ]);

        // AJAX filter changes only need the results part of the page
        return $request->header('X-Partial') === 'results' ? $view->fragment('results') : $view;
    }
}
