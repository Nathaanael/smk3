{{-- One tab per SMK3 element, with the number of (matching) criteria; switching loads that element over AJAX. --}}
@props(['elements', 'counts', 'active', 'search' => null])
<div {{ $attributes->merge(['class' => 'custom-scrollbar overflow-x-auto border-b border-gray-200 dark:border-gray-800']) }}>
    <div role="tablist" aria-label="{{ __('clause.tabsLabel') }}" class="flex min-w-max gap-1 px-5 sm:px-6">
        @foreach ($elements as $element => $name)
            @php($count = $counts[$element] ?? 0)
            <button type="button" role="tab" id="clause-tab-{{ $element }}" aria-controls="clause-panel"
                aria-selected="{{ $element === $active ? 'true' : 'false' }}" title="{{ $name }}"
                x-on:click="selectElement({{ $element }})"
                @disabled($search && ! $count)
                @class([
                    '-mb-px flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                    'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400' => $element === $active,
                    'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90' => $element !== $active,
                ])>
                {{ __('clause.element', ['number' => $element]) }}
                <span @class([
                    'rounded-full px-2 py-0.5 text-theme-xs',
                    'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400' => $element === $active,
                    'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400' => $element !== $active,
                ])>{{ $count }}</span>
            </button>
        @endforeach
    </div>
</div>
