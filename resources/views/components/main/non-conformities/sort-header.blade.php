{{--
    Sortable column header of the findings table. The button carries data-sort; the list
    (nonConformityList.onResultsClick) cycles that column asc → desc → default order.
--}}
@props(['column', 'label', 'sort' => null, 'dir' => 'asc'])
@php
    $active = $sort === $column;
@endphp
<th {{ $attributes->merge(['class' => 'px-4 py-3 text-start text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400']) }}
    @if ($active) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <button type="button" data-sort="{{ $column }}" title="{{ __('nc.sortBy', ['column' => $label]) }}"
        @class([
            'inline-flex items-center gap-1 rounded transition-colors hover:text-gray-800 dark:hover:text-white/90',
            'text-gray-800 dark:text-white/90' => $active,
        ])>
        {{ $label }}
        @if (! $active)
            <x-svg.chevron-up-down class="size-3.5 text-gray-400 dark:text-gray-500" />
        @elseif ($dir === 'desc')
            <x-svg.arrow-down class="size-3 text-brand-500 dark:text-brand-400" />
        @else
            <x-svg.arrow-up class="size-3 text-brand-500 dark:text-brand-400" />
        @endif
    </button>
</th>
