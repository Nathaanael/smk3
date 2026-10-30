{{--
    Progress Monitoring recap: one row per department of the plant plus a total row
    (see App\Services\ProgressMonitor for the scoring). Only the table scrolls, header on top.
--}}
@use('App\Services\ProgressMonitor')
@props(['rows' => [], 'total' => null])
@php
    $headClass = 'px-4 py-3 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-center text-sm whitespace-nowrap text-gray-700 dark:text-gray-400';
    $groupClass = 'border-s border-gray-100 dark:border-gray-800';
    $separator = app()->getLocale() === 'id' ? ',' : '.';
    $number = fn (int|float $value) => rtrim(rtrim(number_format($value, 2, $separator, ''), '0'), $separator);
    $tone = fn (int|float $value) => match (true) {
        $value >= ProgressMonitor::SATISFACTORY => 'success',
        $value >= 60 => 'warning',
        default => 'error',
    };
@endphp
<div {{ $attributes->merge(['class' => 'custom-scrollbar relative max-h-[70vh] max-w-full overflow-auto']) }}>
    <table class="min-w-full">
        <thead class="sticky top-0 z-1 border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
            <tr>
                <th rowspan="2" class="{{ $headClass }} w-12">{{ __('pm.columns.no') }}</th>
                <th rowspan="2" class="{{ $headClass }} text-start">{{ __('nc.columns.department') }}</th>
                <th colspan="4" class="{{ $headClass }} {{ $groupClass }} pb-1 font-semibold text-gray-700 dark:text-gray-300">{{ __('pm.groups.progress') }}</th>
                <th colspan="5" class="{{ $headClass }} {{ $groupClass }} pb-1 font-semibold text-gray-700 dark:text-gray-300">{{ __('pm.groups.deviation') }}</th>
                <th rowspan="2" class="{{ $headClass }} {{ $groupClass }}">{{ __('pm.columns.ranking') }}</th>
            </tr>
            <tr>
                <th class="{{ $headClass }} {{ $groupClass }} pt-1">{{ __('pm.columns.total') }}</th>
                <th class="{{ $headClass }} pt-1">{{ __('nc.closed') }}</th>
                <th class="{{ $headClass }} pt-1">{{ __('nc.open') }}</th>
                <th class="{{ $headClass }} pt-1">{{ __('pm.columns.closedPercent') }}</th>
                <th class="{{ $headClass }} {{ $groupClass }} pt-1">Minor</th>
                <th class="{{ $headClass }} pt-1">Major</th>
                <th class="{{ $headClass }} pt-1">Kritis</th>
                <th class="{{ $headClass }} pt-1">{{ __('pm.columns.score') }}</th>
                <th class="{{ $headClass }} pt-1">{{ __('pm.columns.rating') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($rows as $row)
                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/2">
                    <td class="{{ $cellClass }}">{{ $loop->iteration }}</td>
                    <td class="{{ $cellClass }} text-start font-medium text-gray-800 dark:text-white/90">{{ $row['name'] }}</td>

                    <td class="{{ $cellClass }} {{ $groupClass }}">{{ $row['total'] }}</td>
                    <td class="{{ $cellClass }}">{{ $row['closed'] }}</td>
                    <td class="{{ $cellClass }}">{{ $row['open'] }}</td>
                    <td class="{{ $cellClass }}">
                        @if ($row['total'])
                            <div class="flex min-w-24 flex-col items-center gap-1">
                                <span>{{ $number($row['closed_percent']) }}%</span>
                                <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800" aria-hidden="true">
                                    {{-- width from data: a 0–100 percentage --}}
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, $row['closed_percent']) }}%"></div>
                                </div>
                            </div>
                        @else
                            —
                        @endif
                    </td>

                    <td class="{{ $cellClass }} {{ $groupClass }}">{{ $row['minor'] }}</td>
                    <td class="{{ $cellClass }}">
                        {{ $row['major'] }}
                        @if ($row['converted_major'])
                            <span class="ms-0.5 text-theme-xs font-medium text-warning-600 dark:text-warning-400"
                                title="{{ __('pm.convertedMajor', ['count' => $row['converted_major'], 'min' => ProgressMonitor::MINOR_TO_MAJOR]) }}">+{{ $row['converted_major'] }}</span>
                        @endif
                    </td>
                    <td class="{{ $cellClass }}">{{ $row['kritis'] }}</td>
                    <td class="{{ $cellClass }}">
                        <x-ui.badge size="sm" :color="$tone($row['score'])">{{ $number($row['score']) }}%</x-ui.badge>
                    </td>
                    <td class="{{ $cellClass }}">
                        <x-ui.badge size="sm" :color="$row['satisfactory'] ? 'success' : 'error'">
                            {{ $row['satisfactory'] ? __('pm.satisfactory') : __('pm.poor') }}
                        </x-ui.badge>
                    </td>

                    <td class="{{ $cellClass }} {{ $groupClass }}">
                        <x-ui.badge size="sm" :color="$tone($row['ranking'])">{{ $number($row['ranking']) }}%</x-ui.badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('pm.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows && $total)
            <tfoot class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/2">
                <tr class="font-semibold">
                    <td class="{{ $cellClass }} text-start text-gray-800 dark:text-white/90" colspan="2">{{ __('pm.total') }}</td>
                    <td class="{{ $cellClass }} {{ $groupClass }} text-gray-800 dark:text-white/90">{{ $total['total'] }}</td>
                    <td class="{{ $cellClass }} text-gray-800 dark:text-white/90">{{ $total['closed'] }}</td>
                    <td class="{{ $cellClass }} text-gray-800 dark:text-white/90">{{ $total['open'] }}</td>
                    <td class="{{ $cellClass }} text-gray-800 dark:text-white/90">{{ $total['total'] ? $number($total['closed_percent']).'%' : '—' }}</td>
                    <td class="{{ $cellClass }} {{ $groupClass }} text-gray-800 dark:text-white/90">{{ $total['minor'] }}</td>
                    <td class="{{ $cellClass }} text-gray-800 dark:text-white/90">
                        {{ $total['major'] }}
                        @if ($total['converted_major'])
                            <span class="ms-0.5 text-theme-xs text-warning-600 dark:text-warning-400">+{{ $total['converted_major'] }}</span>
                        @endif
                    </td>
                    <td class="{{ $cellClass }} text-gray-800 dark:text-white/90">{{ $total['kritis'] }}</td>
                    <td class="{{ $cellClass }}" colspan="2"></td>
                    <td class="{{ $cellClass }} {{ $groupClass }}"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
