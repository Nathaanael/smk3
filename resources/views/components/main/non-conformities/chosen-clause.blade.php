{{-- Highlighted box with the clause chosen in findingForm: number, full criterion text and its categories. --}}
<div x-show="selectedClause" x-cloak {{ $attributes }}>
    <div class="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
        <p class="mb-2 flex items-center gap-1.5 text-theme-xs font-medium text-brand-600 dark:text-brand-400">
            <x-svg.check-circle class="size-4" />
            {{ __('nc.create.chosenClause') }}
        </p>
        <p class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="clause.trim()"></p>
        <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300" x-text="selectedClause?.text"></p>
        <div class="mt-3 flex flex-wrap gap-1.5">
            <template x-for="name in selectedClause?.categories ?? []" :key="name">
                <x-ui.badge color="primary" size="sm"><span x-text="name"></span></x-ui.badge>
            </template>
        </div>
    </div>
</div>
