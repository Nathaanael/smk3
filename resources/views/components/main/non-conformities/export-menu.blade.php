{{--
    Export button: opens a panel to tick the columns (shared by the Excel and PDF menus through
    nonConformityList.exportKeys) before downloading. Follows the current search / filters.
--}}
@props(['format', 'label'])
@php $checkboxClass = 'size-4 shrink-0 cursor-pointer rounded border-gray-300 accent-brand-500 dark:border-gray-700'; @endphp
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <x-ui.button variant="outline" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog"
        x-bind:disabled="exporting === '{{ $format }}'">
        <span x-show="exporting === '{{ $format }}'" x-cloak class="size-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
        @if ($format === 'pdf')
            <x-svg.page class="size-5" x-show="exporting !== '{{ $format }}'" />
        @else
            <x-svg.table class="size-5" x-show="exporting !== '{{ $format }}'" />
        @endif
        {{ $label }}
        <x-svg.chevron-down class="size-4 transition-transform" x-bind:class="open && 'rotate-180'" />
    </x-ui.button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms role="dialog" aria-label="{{ $label }}"
        class="absolute end-0 top-full z-99 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('nc.export.chooseColumns') }}</p>
            <label class="flex cursor-pointer items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                <input type="checkbox" class="{{ $checkboxClass }}" x-bind:checked="allExportColumns"
                    x-bind:indeterminate="exportKeys.length > 0 && !allExportColumns" x-on:change="toggleExportColumns($event.target.checked)">
                {{ __('nc.export.allColumns') }}
            </label>
        </div>

        <div class="custom-scrollbar grid max-h-64 grid-cols-2 gap-x-3 gap-y-1 overflow-y-auto">
            <template x-for="column in exportColumnList" :key="column.key">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/3">
                    <input type="checkbox" class="{{ $checkboxClass }}" x-bind:value="column.key" x-model="exportKeys" x-on:change="rememberExportColumns()">
                    <span class="truncate" x-text="column.label"></span>
                </label>
            </template>
        </div>

        <div class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                x-bind:class="selected.length ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'">
                <input type="checkbox" class="{{ $checkboxClass }}" x-model="exportSelectedOnly" x-bind:disabled="!selected.length">
                <span x-text="@js(__('nc.export.onlySelected')).replace(':count', selected.length)"></span>
            </label>
            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-show="!exportSelectedOnly || !selected.length">{{ __('nc.export.followsFilters') }}</p>
        </div>

        <x-ui.button class="mt-4 w-full" x-bind:disabled="!exportKeys.length || exporting"
            x-on:click="open = false; download('{{ $format }}')">
            <x-svg.arrow-down class="size-5" />
            {{ __('nc.export.download', ['format' => $label]) }}
        </x-ui.button>
        <p class="mt-2 text-center text-theme-xs text-error-500" x-show="!exportKeys.length" x-cloak>{{ __('nc.export.noColumns') }}</p>
    </div>
</div>
