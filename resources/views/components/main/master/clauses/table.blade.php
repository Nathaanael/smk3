{{-- Criteria grouped per SMK3 element; category checkboxes save on click (clauseMaster.toggle). --}}
@use('App\Models\Smk3Criterion')
@props(['groups', 'elements' => []])
@php
    $headClass = 'px-4 py-3 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
    $number = 0;
@endphp
<div {{ $attributes->merge(['class' => 'custom-scrollbar max-w-full overflow-x-auto']) }}>
    <table class="min-w-full">
        <thead class="border-y border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-white/2">
            <tr>
                <th rowspan="2" class="{{ $headClass }} w-14 text-center">{{ __('clause.columns.no') }}</th>
                <th rowspan="2" class="{{ $headClass }} w-28 text-start whitespace-nowrap">{{ __('clause.columns.number') }}</th>
                <th rowspan="2" class="{{ $headClass }} min-w-96 text-start">{{ __('clause.columns.criterion') }}</th>
                <th colspan="3" class="{{ $headClass }} border-s border-gray-100 pb-1 text-center dark:border-gray-800">{{ __('clause.columns.category') }}</th>
                <th rowspan="2" class="{{ $headClass }} w-24 border-s border-gray-100 text-center dark:border-gray-800">{{ __('clause.columns.actions') }}</th>
            </tr>
            <tr>
                @foreach (Smk3Criterion::CATEGORIES as $label)
                    <th @class([$headClass, 'w-20 pt-1 text-center', 'border-s border-gray-100 dark:border-gray-800' => $loop->first])>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>

        @foreach ($groups as $element => $criteria)
            <tbody class="divide-y divide-gray-100 border-b border-gray-100 dark:divide-gray-800 dark:border-gray-800" x-show="isElementVisible({{ $element }})">
                <tr class="bg-gray-50 dark:bg-white/3">
                    <td colspan="7" class="px-4 py-2.5 text-sm font-semibold text-gray-800 dark:text-white/90">
                        {{ __('clause.element', ['number' => $element]) }}
                        @isset($elements[$element])
                            <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $elements[$element] }}</span>
                        @endisset
                    </td>
                </tr>

                @foreach ($criteria as $criterion)
                    <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/2" x-show="isVisible({{ $criterion->id }})">
                        <td class="{{ $cellClass }} text-center text-gray-500 dark:text-gray-500">{{ ++$number }}</td>
                        <td class="{{ $cellClass }} font-semibold whitespace-nowrap text-gray-800 dark:text-white/90">{{ $criterion->no_kriteria }}</td>
                        <td class="{{ $cellClass }} leading-relaxed">{{ $criterion->kriteria }}</td>
                        @foreach (Smk3Criterion::CATEGORIES as $column => $label)
                            <td @class([$cellClass, 'text-center', 'border-s border-gray-100 dark:border-gray-800' => $loop->first])>
                                <div class="flex justify-center">
                                    <x-ui.checkbox model="rows[{{ $criterion->id }}].{{ $column }}" class="size-5 rounded-md"
                                        aria-label="{{ $label }} {{ $criterion->no_kriteria }}"
                                        x-bind:aria-busy="Boolean(saving['{{ $criterion->id }}.{{ $column }}'])"
                                        x-on:change="toggle({{ $criterion->id }}, '{{ $column }}', $event.detail.checked)" />
                                </div>
                            </td>
                        @endforeach
                        <td class="{{ $cellClass }} border-s border-gray-100 dark:border-gray-800">
                            <div class="flex items-center justify-center gap-1">
                                <x-ui.button variant="ghost" size="icon-sm" x-on:click="openEdit({{ $criterion->id }})" aria-label="{{ __('clause.edit') }} {{ $criterion->no_kriteria }}" title="{{ __('clause.edit') }}">
                                    <x-svg.pencil class="size-4" />
                                </x-ui.button>
                                <x-ui.button variant="ghost" size="icon-sm" class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10"
                                    x-on:click="openDelete({{ $criterion->id }})" aria-label="{{ __('clause.delete') }} {{ $criterion->no_kriteria }}" title="{{ __('clause.delete') }}">
                                    <x-svg.trash-bin class="size-4" />
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        @endforeach

        <tbody x-show="visibleCount === 0" x-cloak>
            <tr>
                <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('clause.empty') }}</td>
            </tr>
        </tbody>
    </table>
</div>
