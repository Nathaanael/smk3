{{--
    Explains how the Progress Monitoring recap is scored (App\Services\ProgressMonitor):
    one block per measure, the colour bands of the badges and, in the slot, the worked example.
--}}
@use('App\Services\ProgressMonitor')
@props(['criteria' => 166])
@php
    $satisfactory = ProgressMonitor::SATISFACTORY;
    $repeat = ProgressMonitor::MINOR_TO_MAJOR;
    $weight = ProgressMonitor::MAJOR_WEIGHT;

    $measures = [
        ['icon' => 'bar-chart', 'tone' => 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
            'title' => __('pm.columns.score'), 'formula' => __('pm.guide.score.formula', ['criteria' => $criteria, 'weight' => $weight]),
            'text' => __('pm.guide.score.text', ['criteria' => $criteria, 'weight' => $weight])],
        ['icon' => 'octagon-alert', 'tone' => 'bg-error-50 text-error-500 dark:bg-error-500/15 dark:text-error-400',
            'title' => __('pm.guide.penalty.title'), 'formula' => __('pm.guide.penalty.formula', ['weight' => $weight]),
            'text' => __('pm.guide.penalty.text', ['min' => $repeat, 'weight' => $weight])],
        ['icon' => 'shield-check', 'tone' => 'bg-success-50 text-success-500 dark:bg-success-500/15 dark:text-success-400',
            'title' => __('pm.columns.rating'), 'formula' => __('pm.guide.rating.formula', ['min' => $satisfactory]),
            'text' => __('pm.guide.rating.text')],
        ['icon' => 'check-circle', 'tone' => 'bg-warning-50 text-warning-500 dark:bg-warning-500/15 dark:text-warning-400',
            'title' => __('pm.columns.ranking'), 'formula' => __('pm.guide.ranking.formula'),
            'text' => __('pm.guide.ranking.text')],
    ];
@endphp
<x-common.component-card :title="__('pm.guide.title')" :desc="__('pm.guide.description')" {{ $attributes }}>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($measures as $measure)
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $measure['tone'] }}">
                        <x-dynamic-component :component="'svg.'.$measure['icon']" class="size-5" />
                    </span>
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $measure['title'] }}</h4>
                </div>
                <p class="rounded-lg bg-gray-50 px-3 py-2 font-mono text-theme-xs text-gray-800 dark:bg-white/3 dark:text-white/90">{{ $measure['formula'] }}</p>
                <p class="text-theme-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $measure['text'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- colour bands of the Score / Total Ranking badges --}}
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <h4 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('pm.guide.colors') }}</h4>
            <ul class="flex flex-col gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                <li class="flex items-center gap-3">
                    <x-ui.badge size="sm" color="success" class="w-20 justify-center">≥ {{ $satisfactory }}%</x-ui.badge>
                    {{ __('pm.guide.colorGood') }}
                </li>
                <li class="flex items-center gap-3">
                    <x-ui.badge size="sm" color="warning" class="w-20 justify-center">60–{{ $satisfactory - 1 }}%</x-ui.badge>
                    {{ __('pm.guide.colorFair') }}
                </li>
                <li class="flex items-center gap-3">
                    <x-ui.badge size="sm" color="error" class="w-20 justify-center">&lt; 60%</x-ui.badge>
                    {{ __('pm.guide.colorPoor') }}
                </li>
            </ul>
            <p class="mt-3 text-theme-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ __('pm.guide.colorsNote') }}</p>
        </div>

        {{-- the worked example follows the filters, so monitor.js replaces it on every reload --}}
        <div x-ref="example" x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="rounded-xl border border-gray-200 p-4 transition-opacity dark:border-gray-800">
            {{ $slot }}
        </div>
    </div>
</x-common.component-card>
