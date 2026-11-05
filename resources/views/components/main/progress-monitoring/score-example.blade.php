{{--
    Worked example of the scoring guide with real data: the first department of the recap that
    has findings, preferably without an open Kritis so the formulas show. Reloaded with the filters.
--}}
@use('App\Services\ProgressMonitor')
@props(['rows' => [], 'criteria' => 166])
@php
    $separator = app()->getLocale() === 'id' ? ',' : '.';
    $number = fn (int|float $value) => rtrim(rtrim(number_format($value, 2, $separator, ''), '0'), $separator);
    $termClass = 'text-gray-500 dark:text-gray-400';
    $resultClass = 'font-semibold text-gray-800 dark:text-white/90';

    $withFindings = collect($rows)->where('total', '>', 0);
    $row = $withFindings->firstWhere('kritis', 0) ?? $withFindings->first();
@endphp
<div data-score-example>
    <h4 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('pm.guide.example') }}</h4>
    @if ($row)
        @php
            $majors = $row['major'] + $row['converted_major'];
            $hasKritis = $row['kritis'] > 0;
        @endphp
        <p class="mb-3 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('pm.guide.exampleCase', [
            'department' => $row['name'],
            'total' => $row['total'],
            'closed' => $row['closed'],
            'minor' => $row['minor'],
            'major' => $row['converted_major'] ? "{$row['major']} (+{$row['converted_major']})" : $row['major'],
            'kritis' => $row['kritis'],
        ]) }}</p>
        <dl class="flex flex-col gap-1.5 font-mono text-theme-xs text-gray-700 dark:text-gray-300">
            <div class="flex flex-wrap gap-x-2">
                <dt class="{{ $termClass }}">{{ __('pm.columns.closedPercent') }}</dt>
                <dd>= {{ $row['closed'] }} / {{ $row['total'] }} × 100 = <span class="{{ $resultClass }}">{{ $number($row['closed_percent']) }}%</span></dd>
            </div>
            <div class="flex flex-wrap gap-x-2">
                <dt class="{{ $termClass }}">{{ __('pm.columns.score') }}</dt>
                @if ($hasKritis)
                    <dd>= {{ __('pm.guide.exampleKritis') }} = <span class="{{ $resultClass }}">0%</span></dd>
                @else
                    <dd>= ({{ $criteria }} − ({{ $row['minor'] }} + {{ $majors }} × {{ ProgressMonitor::MAJOR_WEIGHT }})) / {{ $criteria }} × 100 = <span class="{{ $resultClass }}">{{ $number($row['score']) }}%</span></dd>
                @endif
            </div>
            <div class="flex flex-wrap gap-x-2">
                <dt class="{{ $termClass }}">{{ __('pm.columns.ranking') }}</dt>
                @if ($hasKritis)
                    <dd>= {{ __('pm.guide.exampleKritis') }} = <span class="{{ $resultClass }}">0%</span></dd>
                @else
                    <dd>= ({{ $number($row['closed_percent']) }} + {{ $number($row['score']) }}) / 2 = <span class="{{ $resultClass }}">{{ $number($row['ranking']) }}%</span></dd>
                @endif
            </div>
            <div class="flex flex-wrap gap-x-2">
                <dt class="{{ $termClass }}">{{ __('pm.columns.rating') }}</dt>
                <dd><x-ui.badge size="sm" :color="$row['satisfactory'] ? 'success' : 'error'">{{ $row['satisfactory'] ? __('pm.satisfactory') : __('pm.poor') }}</x-ui.badge></dd>
            </div>
        </dl>
    @else
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('pm.guide.exampleEmpty') }}</p>
    @endif
</div>
