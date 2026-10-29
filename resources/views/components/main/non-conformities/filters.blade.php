{{--
    Search box + "Filter" button with a panel holding every filter. State lives in the
    surrounding nonConformityList Alpine component (resources/js/components/non-conformities/list.js).
--}}
@props(['filters' => [], 'plants' => [], 'categories' => []])
@php
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center']) }}>
    <div class="relative flex-1">
        <x-svg.search class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
        <x-ui.input type="search" x-model="q" x-on:input.debounce.400ms="apply()" placeholder="{{ __('nc.searchPlaceholder') }}"
            aria-label="{{ __('nc.searchPlaceholder') }}" class="ps-12" />
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
                @if (count($plants) > 1)
                    <div>
                        <label for="filter-pabrik" class="{{ $labelClass }}">{{ __('nc.plant') }}</label>
                        <x-ui.native-select id="filter-pabrik" x-model="pabrik" x-on:change="changePlant()">
                            <option value="">{{ __('nc.allPlants') }}</option>
                            @foreach ($plants as $code => $name)
                                <option value="{{ $code }}" @selected(($filters['pabrik'] ?? '') === $code)>{{ $name }}</option>
                            @endforeach
                        </x-ui.native-select>
                    </div>
                @endif

                <div>
                    <label for="filter-departemen" class="{{ $labelClass }}">{{ __('nc.department') }}</label>
                    <x-ui.native-select id="filter-departemen" x-model="departemen" x-on:change="apply()">
                        <option value="">{{ __('nc.allDepartments') }}</option>
                        <template x-for="[code, name] in departmentOptions" :key="code">
                            <option :value="code" x-text="name" :selected="code === departemen"></option>
                        </template>
                    </x-ui.native-select>
                </div>

                <div>
                    <label for="filter-kategori" class="{{ $labelClass }}">{{ __('nc.category') }}</label>
                    <x-ui.native-select id="filter-kategori" x-model="kategori" x-on:change="apply()">
                        <option value="">{{ __('nc.allCategories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['kategori'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </x-ui.native-select>
                </div>

                <div>
                    <label for="filter-status" class="{{ $labelClass }}">{{ __('nc.status') }}</label>
                    <x-ui.native-select id="filter-status" x-model="status" x-on:change="apply()">
                        <option value="">{{ __('nc.allStatuses') }}</option>
                        <option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('nc.open') }}</option>
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>{{ __('nc.closed') }}</option>
                    </x-ui.native-select>
                </div>

                {{-- two separate dates; each limits the other so "to" cannot fall before "from" --}}
                <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2" x-ref="dateRange">
                    <div x-on:date-change="dari = $event.detail.value; apply()">
                        <label for="filter-dari" class="{{ $labelClass }}">{{ __('nc.dateFrom') }}</label>
                        <x-common.date-input id="filter-dari" :value="$filters['dari'] ?? null" :placeholder="__('nc.anyDate')" max="sampai" />
                    </div>
                    <div x-on:date-change="sampai = $event.detail.value; apply()">
                        <label for="filter-sampai" class="{{ $labelClass }}">{{ __('nc.dateTo') }}</label>
                        <x-common.date-input id="filter-sampai" :value="$filters['sampai'] ?? null" :placeholder="__('nc.anyDate')" min="dari" />
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
