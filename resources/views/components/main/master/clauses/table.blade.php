{{-- Criteria of the active element; changed category checkboxes are saved with clauseMaster.save(). --}}
@use('App\Models\Smk3Criterion')
@props(['criteria', 'search' => null])
@php
    $headClass = 'px-4 py-3 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes->merge(['class' => 'custom-scrollbar max-w-full overflow-x-auto']) }}>
    <table class="min-w-full">
        <thead class="border-b border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-white/2">
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

        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($criteria as $criterion)
                <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/2"
                    x-bind:class="isDirty({{ $criterion->id }}) && 'bg-warning-50/60 hover:bg-warning-50 dark:bg-warning-500/5 dark:hover:bg-warning-500/10'">
                    <td class="{{ $cellClass }} text-center text-gray-500 dark:text-gray-500">{{ $loop->iteration }}</td>
                    <td class="{{ $cellClass }} font-semibold whitespace-nowrap text-gray-800 dark:text-white/90">{{ $criterion->no_kriteria }}</td>
                    <td class="{{ $cellClass }} leading-relaxed">
                        {{ $criterion->kriteria }}
                        @if ($criterion->keywords())
                            <span class="mt-1.5 flex flex-wrap gap-1" title="{{ __('clause.form.keywords') }}">
                                @foreach ($criterion->keywords() as $keyword)
                                    <x-ui.badge color="gray" size="sm">{{ $keyword }}</x-ui.badge>
                                @endforeach
                            </span>
                        @endif
                    </td>
                    @foreach (Smk3Criterion::CATEGORIES as $column => $label)
                        <td @class([$cellClass, 'text-center', 'border-s border-gray-100 dark:border-gray-800' => $loop->first])>
                            <div class="flex justify-center">
                                <x-ui.checkbox model="rows[{{ $criterion->id }}]?.{{ $column }}" class="size-5 rounded-md"
                                    aria-label="{{ $label }} {{ $criterion->no_kriteria }}"
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
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ $search ? __('clause.empty') : __('clause.emptyElement') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
