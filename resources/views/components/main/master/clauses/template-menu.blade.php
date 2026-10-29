{{--
    "Download Template" button: opens a panel to tick the SMK3 elements first, then downloads
    in the background (clauseMaster.downloadTemplate) behind a loading toast.
--}}
@props(['elements' => []])
@php $checkboxClass = 'size-4 shrink-0 cursor-pointer rounded border-gray-300 accent-brand-500 dark:border-gray-700'; @endphp
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <x-ui.button variant="outline" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog"
        x-bind:disabled="downloadingTemplate">
        <span x-show="downloadingTemplate" x-cloak class="size-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
        <x-svg.arrow-down class="size-5" x-show="!downloadingTemplate" />
        {{ __('clause.download') }}
        <x-svg.chevron-down class="size-4 transition-transform" x-bind:class="open && 'rotate-180'" />
    </x-ui.button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms role="dialog" aria-label="{{ __('clause.template.chooseElements') }}"
        class="absolute start-0 top-full z-99 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ __('clause.template.chooseElements') }}</p>
            <label class="flex cursor-pointer items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                <input type="checkbox" class="{{ $checkboxClass }}" x-bind:checked="allElementsChosen"
                    x-bind:indeterminate="chosenElements.length > 0 && !allElementsChosen" x-on:change="chooseAllElements($event.target.checked)">
                {{ __('nc.export.allColumns') }}
            </label>
        </div>

        <div class="custom-scrollbar flex max-h-72 flex-col gap-0.5 overflow-y-auto">
            @foreach ($elements as $number => $element)
                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/3">
                    <input type="checkbox" class="{{ $checkboxClass }} mt-0.5" value="{{ $number }}" x-model="chosenElements">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ __('clause.element', ['number' => $number]) }}</span>
                        @if ($element['name'])
                            <span class="block truncate text-theme-xs text-gray-500 dark:text-gray-400" title="{{ $element['name'] }}">{{ $element['name'] }}</span>
                        @endif
                    </span>
                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">{{ $element['count'] }}</span>
                </label>
            @endforeach
        </div>

        <p class="mt-3 border-t border-gray-100 pt-3 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('clause.template.partialHint') }}</p>

        <x-ui.button class="mt-3 w-full" x-bind:disabled="!chosenElements.length || downloadingTemplate" x-on:click="open = false; downloadTemplate()">
            <x-svg.arrow-down class="size-5" />
            <span x-text="allElementsChosen ? @js(__('clause.template.downloadAll')) : @js(__('clause.template.downloadSome')).replace(':count', chosenElements.length)"></span>
        </x-ui.button>
        <p class="mt-2 text-center text-theme-xs text-error-500" x-show="!chosenElements.length" x-cloak>{{ __('clause.template.noElements') }}</p>
    </div>
</div>
