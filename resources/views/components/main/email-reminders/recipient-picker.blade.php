{{--
    Choose who gets the reminder. State lives in the surrounding emailReminders Alpine component
    (resources/js/components/email-reminders/page.js), so every picker shows the same choice.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <x-ui.input type="search" x-model="personSearch" x-on:keydown.enter.prevent="" placeholder="{{ __('reminder.picker.search') }}" aria-label="{{ __('reminder.picker.search') }}" />

    <div class="flex flex-wrap items-center justify-between gap-2 text-theme-xs">
        <span class="font-medium text-gray-700 dark:text-gray-300"
            x-text="@js(__('reminder.picker.count')).replace(':picked', pickedIds.length).replace(':total', people.length)"></span>
        <span class="flex items-center gap-3">
            <button type="button" x-on:click="pickVisible(true)" class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">{{ __('reminder.picker.selectAll') }}</button>
            <button type="button" x-on:click="pickVisible(false)" class="font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">{{ __('reminder.picker.clear') }}</button>
        </span>
    </div>

    <ul class="custom-scrollbar max-h-60 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
        <template x-for="person in visiblePeople" :key="person.id">
            <li>
                <label class="flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors hover:bg-gray-50 dark:hover:bg-white/5">
                    <input type="checkbox" class="size-4 shrink-0 cursor-pointer rounded border-gray-300 accent-brand-500 dark:border-gray-700"
                        x-bind:checked="isPicked(person.id)" x-on:change="togglePerson(person.id, $event.target.checked)">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="person.name"></span>
                        <span class="block truncate text-theme-xs text-gray-500 dark:text-gray-400" x-bind:title="`${person.email} · ${person.departments}`" x-text="`${person.email} · ${person.departments}`"></span>
                    </span>
                    <span class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400" x-text="@js(__('reminder.picker.findings')).replace(':count', person.findings)"></span>
                </label>
            </li>
        </template>
        <li x-show="!visiblePeople.length" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
            x-text="people.length ? @js(__('reminder.picker.noMatch')) : @js(__('reminder.picker.empty'))"></li>
    </ul>
</div>
