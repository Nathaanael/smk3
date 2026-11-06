{{--
    One history tab of the email reminder page: search and filters above the results, which reload
    over AJAX (resources/js/components/email-reminders/history.js).
--}}
@props(['status', 'emails', 'senders' => []])
@php
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes }} x-data="reminderHistory({ url: @js(route('email-reminders.history', $status)) })" x-on:reminder-history-refresh.window="apply()">
    <div class="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-center sm:px-6">
        <div class="relative flex-1">
            <x-svg.search class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
            <x-ui.input type="search" x-model="q" x-on:input.debounce.400ms="apply()" placeholder="{{ __('reminder.history.search') }}"
                aria-label="{{ __('reminder.history.search') }}" class="ps-12" />
        </div>

        {{-- the date picker calendar is attached to <body>, so clicks inside it must not close the panel --}}
        <div class="relative" x-on:keydown.escape="filterOpen = false"
            x-on:click.outside="if (!$event.target.closest('.flatpickr-calendar')) filterOpen = false">
            <x-ui.button variant="outline" class="w-full sm:w-auto" x-on:click="filterOpen = !filterOpen" x-bind:aria-expanded="filterOpen" aria-haspopup="true">
                <x-svg.horizontal-slide class="size-5" />
                {{ __('nc.filter') }}
                <span x-show="activeFilterCount" x-cloak x-text="activeFilterCount"
                    class="flex size-5 items-center justify-center rounded-full bg-brand-500 text-theme-xs font-medium text-white"></span>
            </x-ui.button>

            <div x-show="filterOpen" x-cloak x-transition.origin.top.right
                class="absolute end-0 top-full z-99 mt-2 w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="history-{{ $status }}-sender" class="{{ $labelClass }}">{{ __('reminder.history.sentBy') }}</label>
                        <x-ui.native-select id="history-{{ $status }}-sender" x-model="sender" x-on:change="apply()">
                            <option value="">{{ __('reminder.history.allSenders') }}</option>
                            @foreach ($senders as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-ui.native-select>
                    </div>

                    {{-- two separate dates; each limits the other so "to" cannot fall before "from" --}}
                    <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2" x-ref="dateRange">
                        <div x-on:date-change="dari = $event.detail.value; apply()">
                            <label for="history-{{ $status }}-dari" class="{{ $labelClass }}">{{ __('nc.dateFrom') }}</label>
                            <x-common.date-input id="history-{{ $status }}-dari" :placeholder="__('nc.anyDate')" max="sampai" />
                        </div>
                        <div x-on:date-change="sampai = $event.detail.value; apply()">
                            <label for="history-{{ $status }}-sampai" class="{{ $labelClass }}">{{ __('nc.dateTo') }}</label>
                            <x-common.date-input id="history-{{ $status }}-sampai" :placeholder="__('nc.anyDate')" min="dari" />
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <button type="button" x-on:click="reset()" x-bind:disabled="!activeFilterCount"
                        class="text-sm font-medium text-gray-500 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-400 dark:hover:text-white/90">
                        {{ __('nc.reset') }}
                    </button>
                    <x-ui.button size="sm" x-on:click="filterOpen = false">{{ __('nc.done') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>

    <div x-ref="results" x-on:click="onResultsClick($event)" x-on:change="onResultsChange($event)"
        x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
        <x-main.email-reminders.history-list :emails="$emails" :status="$status" />
    </div>
</div>
