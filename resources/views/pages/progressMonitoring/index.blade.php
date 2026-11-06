@extends('layouts.app')

@section('content')
    <div x-data="progressMonitor({
        url: @js(route('progress-monitoring')),
        filters: @js($filters),
        currentMonth: @js($currentMonth),
    })">
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="flex flex-col gap-1 px-5 pt-5 sm:px-6 sm:pt-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('smk3.progressMonitoring') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('smk3.progressMonitoringDesc') }}</p>
        </div>

        <x-main.progress-monitoring.filters class="px-5 py-5 sm:px-6" :plants="$plants" :filters="$filters" />

        <div x-ref="results" x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
            @fragment('results')
                <x-main.progress-monitoring.period-label
                    class="border-t border-gray-100 px-5 py-3 sm:px-6 dark:border-gray-800"
                    :plant="$plants[$filters['pabrik']] ?? null"
                    :from="$filters['dari']"
                    :to="$filters['sampai']"
                />

                <x-main.progress-monitoring.table :rows="$recap['rows']" :total="$recap['total']" />
            @endfragment
        </div>
    </div>

    <x-main.progress-monitoring.scoring-guide class="mt-6" :criteria="$recap['criteria'] ?: 166">
        @fragment('example')
            <x-main.progress-monitoring.score-example :rows="$recap['rows']" :criteria="$recap['criteria'] ?: 166" />
        @endfragment
    </x-main.progress-monitoring.scoring-guide>
    </div>
@endsection
