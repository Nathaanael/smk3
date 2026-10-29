{{-- Clause suggestions for the description (findingForm); picking one fills the clause number. --}}
<div {{ $attributes->merge(['aria-live' => 'polite']) }}>
    <p class="mb-2 flex items-center gap-2 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('nc.suggest.title') }}
        <span x-show="suggesting" x-cloak class="size-3 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></span>
    </p>

    <p x-show="!suggestions.length && !suggesting" class="rounded-lg border border-dashed border-gray-300 px-3.5 py-3 text-theme-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
        x-text="description.trim().length < 3 ? @js(__('nc.suggest.typeFirst')) : @js(__('nc.suggest.none'))"></p>

    <div class="flex flex-col gap-2">
        <template x-for="suggestion in suggestions" :key="suggestion.no">
            <button type="button" x-on:click="pickClause(suggestion.no)"
                class="flex w-full items-start gap-3 rounded-lg border border-gray-200 px-3.5 py-2.5 text-start transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/3"
                x-bind:class="clause.trim() === suggestion.no && 'border-brand-300 bg-brand-50 dark:border-brand-500/40 dark:bg-brand-500/10'"
                x-bind:aria-pressed="clause.trim() === suggestion.no ? 'true' : 'false'">
                <span class="w-12 shrink-0 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="suggestion.no"></span>
                <span class="min-w-0 flex-1">
                    <span class="line-clamp-2 text-sm text-gray-700 dark:text-gray-300" x-text="suggestion.text"></span>
                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400" x-text="suggestion.reason"></span>
                </span>
            </button>
        </template>
    </div>
</div>
