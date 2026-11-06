@extends('layouts.app')

@section('content')
    <!-- <x-common.page-breadcrumb pageTitle="Dashboard" /> -->

    <x-main.dashboard.greeting-clock :name="auth()->user()->name" />

    <div class="mt-6" x-data="dashboardSummary({
        url: @js(route('dashboard.index')),
        filters: @js($filters),
        currentMonth: @js($currentMonth),
    })">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3">
            <x-main.progress-monitoring.filters :plants="$plants" :filters="$filters" />
        </div>

        <div x-ref="results" x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
            @fragment('results')
                <x-main.progress-monitoring.period-label
                    class="mt-6"
                    :plant="$plants[$filters['pabrik']] ?? null"
                    :from="$filters['dari']"
                    :to="$filters['sampai']"
                />

                @php($period = ['plant' => $plants[$filters['pabrik']] ?? null, 'from' => $filters['dari'], 'to' => $filters['sampai']])
                @if ($summary)
                    <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <x-main.dashboard.chart-card
                            :title="__('dashboard.score.title')"
                            :description="__('dashboard.score.description', ['value' => \App\Services\ProgressMonitor::SATISFACTORY])"
                            chart="smk3-score"
                            :period="$period"
                            :data="$summary['score']"
                        >
                            <x-slot:legend>
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-xs bg-success-600 dark:bg-success-500" aria-hidden="true"></span>
                                    {{ __('pm.satisfactory') }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-xs bg-error-600 dark:bg-error-500" aria-hidden="true"></span>
                                    {{ __('pm.poor') }}
                                </span>
                            </x-slot:legend>
                        </x-main.dashboard.chart-card>

                        <x-main.dashboard.chart-card
                            :title="__('dashboard.open.title')"
                            :description="__('dashboard.open.description')"
                            chart="smk3-stacked"
                            :period="$period"
                            :data="$summary['open']"
                        />

                        <x-main.dashboard.chart-card
                            :title="__('dashboard.repeated.title')"
                            :description="__('dashboard.repeated.description', ['min' => \App\Services\ProgressMonitor::MINOR_TO_MAJOR, 'limit' => \App\Services\DashboardSummary::REPEATED_CHART_LIMIT])"
                            chart="smk3-stacked"
                            :period="$period"
                            :data="$summary['repeated']"
                            :empty-message="__('dashboard.repeated.empty')"
                        />

                        <x-main.dashboard.chart-card
                            :title="__('dashboard.repairPhotos.title')"
                            :description="__('dashboard.repairPhotos.description')"
                            chart="smk3-stacked"
                            :period="$period"
                            :data="$summary['repairPhotos']"
                        />

                        <x-main.dashboard.chart-card
                            :title="__('dashboard.trend.title')"
                            :description="__('dashboard.trend.description')"
                            chart="smk3-stacked"
                            :period="$period"
                            :data="$summary['trend']"
                        />

                        <x-main.dashboard.chart-card
                            :title="__('dashboard.aging.title')"
                            :description="__('dashboard.aging.description')"
                            chart="smk3-stacked"
                            :period="$period"
                            :data="$summary['aging']"
                        />
                    </div>
                @endif
            @endfragment
        </div>
    </div>
@endsection
