{{--
    Explains how the Progress Monitoring recap is scored (App\Services\ProgressMonitor):
    one block per measure, the colour bands of the badges and a worked example.
--}}
@use('App\Services\ProgressMonitor')
@props(['criteria' => 166])
@php
    $separator = app()->getLocale() === 'id' ? ',' : '.';
    $number = fn (int|float $value) => rtrim(rtrim(number_format($value, 2, $separator, ''), '0'), $separator);
    $satisfactory = ProgressMonitor::SATISFACTORY;
    $repeat = ProgressMonitor::MINOR_TO_MAJOR;

    // worked example: 4 Minor findings on different clauses, 3 of them closed
    $exampleScore = round(($criteria - 4) / $criteria * 100, 2);
    $exampleRanking = round((75 + $exampleScore) / 2, 2);

    $measures = [
        ['icon' => 'bar-chart', 'tone' => 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
            'title' => __('pm.columns.score'), 'formula' => __('pm.guide.score.formula', ['criteria' => $criteria]),
            'text' => __('pm.guide.score.text', ['criteria' => $criteria])],
        ['icon' => 'octagon-alert', 'tone' => 'bg-error-50 text-error-500 dark:bg-error-500/15 dark:text-error-400',
            'title' => __('pm.guide.penalty.title'), 'formula' => __('pm.guide.penalty.formula'),
            'text' => __('pm.guide.penalty.text', ['min' => $repeat])],
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
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <h4 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('pm.guide.example') }}</h4>
            <p class="mb-3 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('pm.guide.exampleCase') }}</p>
            <dl class="flex flex-col gap-1.5 font-mono text-theme-xs text-gray-700 dark:text-gray-300">
                <div class="flex flex-wrap gap-x-2">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('pm.columns.closedPercent') }}</dt>
                    <dd>= 3 / 4 × 100 = <span class="font-semibold text-gray-800 dark:text-white/90">75%</span></dd>
                </div>
                <div class="flex flex-wrap gap-x-2">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('pm.columns.score') }}</dt>
                    <dd>= ({{ $criteria }} − 4) / {{ $criteria }} × 100 = <span class="font-semibold text-gray-800 dark:text-white/90">{{ $number($exampleScore) }}%</span></dd>
                </div>
                <div class="flex flex-wrap gap-x-2">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('pm.columns.ranking') }}</dt>
                    <dd>= (75 + {{ $number($exampleScore) }}) / 2 = <span class="font-semibold text-gray-800 dark:text-white/90">{{ $number($exampleRanking) }}%</span></dd>
                </div>
                <div class="flex flex-wrap gap-x-2">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('pm.columns.rating') }}</dt>
                    <dd><x-ui.badge size="sm" :color="$exampleScore >= $satisfactory ? 'success' : 'error'">{{ $exampleScore >= $satisfactory ? __('pm.satisfactory') : __('pm.poor') }}</x-ui.badge></dd>
                </div>
            </dl>
        </div>
    </div>
</x-common.component-card>
