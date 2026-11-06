{{--
    A dashboard chart with its title and a button that opens the same numbers as a table.
    $chart: file name in resources/js/components/chart/dashboard; $data: see App\Services\DashboardSummary.
--}}
@props(['title', 'description' => null, 'chart', 'data', 'legend' => null, 'emptyMessage' => null, 'period' => []])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3']) }}>
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
    @if ($description)
        {{-- room for two lines, so cards side by side start their charts at the same height --}}
        <p class="mt-1 text-sm text-gray-500 xl:min-h-10 dark:text-gray-400">{{ $description }}</p>
    @endif

    @if ($data['empty'])
        <p class="py-16 text-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage ?? __('dashboard.noData') }}</p>
    @else
        @if ($legend)
            <div class="mt-4 flex flex-wrap items-center gap-4 text-theme-xs text-gray-600 dark:text-gray-400">{{ $legend }}</div>
        @endif

        <div class="mt-4" data-dashboard-chart="{{ $chart }}" data-config="{{ json_encode($data['config']) }}"></div>

        <x-main.dashboard.table-dialog :title="$title" :table="$data['table']" :period="$period" />
    @endif
</section>
