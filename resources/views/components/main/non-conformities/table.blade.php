{{--
    Findings table. With canManage (admin, auditor) rows get a checkbox for bulk actions and an
    edit / delete column; everyone who sees a row may upload its repair photo.
--}}
@props(['findings', 'showPlant' => false, 'canManage' => false])
@php
    $headClass = 'px-4 py-3 text-start text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
    $checkboxClass = 'size-4 cursor-pointer rounded border-gray-300 accent-brand-500 dark:border-gray-700';
    $columns = 10 + ($showPlant ? 1 : 0) + ($canManage ? 2 : 0);
    $categoryColor = fn (?string $category) => match ($category) {
        'Kritis' => 'error',
        'Major' => 'warning',
        'Minor' => 'info',
        default => 'gray',
    };
@endphp
<div {{ $attributes->merge(['class' => 'custom-scrollbar max-w-full overflow-x-auto']) }}>
    <table class="min-w-full">
        <thead class="border-y border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-white/2">
            <tr>
                @if ($canManage)
                    <th class="w-10 px-4 py-3">
                        <input type="checkbox" class="{{ $checkboxClass }}" aria-label="{{ __('nc.bulk.selectAll') }}"
                            x-bind:checked="allSelected" x-bind:indeterminate="someSelected" x-bind:disabled="!pageIds.length"
                            x-on:change="toggleAll($event.target.checked)">
                    </th>
                @endif
                <th class="{{ $headClass }}">{{ __('nc.columns.id') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.date') }}</th>
                @if ($showPlant)
                    <th class="{{ $headClass }}">{{ __('nc.columns.plant') }}</th>
                @endif
                <th class="{{ $headClass }}">{{ __('nc.columns.department') }}</th>
                <th class="{{ $headClass }} min-w-72">{{ __('nc.columns.finding') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.findingPhoto') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.clause') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.category') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.pic') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.repairPhoto') }}</th>
                <th class="{{ $headClass }}">{{ __('nc.columns.status') }}</th>
                @if ($canManage)
                    <th class="{{ $headClass }} text-center">{{ __('clause.columns.actions') }}</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($findings as $finding)
                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/2"
                    @if ($canManage) x-bind:class="selected.includes('{{ $finding->id }}') && 'bg-brand-50/60 hover:bg-brand-50 dark:bg-brand-500/5 dark:hover:bg-brand-500/10'" @endif>
                    @if ($canManage)
                        <td class="px-4 py-3">
                            <input type="checkbox" class="{{ $checkboxClass }}" value="{{ $finding->id }}" x-model="selected"
                                aria-label="{{ __('nc.bulk.select', ['id' => $finding->id]) }}">
                        </td>
                    @endif
                    <td class="{{ $cellClass }} font-medium whitespace-nowrap text-gray-800 dark:text-white/90">#{{ $finding->id }}</td>
                    <td class="{{ $cellClass }} whitespace-nowrap">{{ $finding->tanggal->translatedFormat('d M Y') }}</td>
                    @if ($showPlant)
                        <td class="{{ $cellClass }} whitespace-nowrap">{{ strtoupper($finding->pabrik) }}</td>
                    @endif
                    <td class="{{ $cellClass }} whitespace-nowrap">{{ $finding->departmentName() }}</td>
                    <td class="{{ $cellClass }}">
                        <p class="line-clamp-2" title="{{ $finding->deskripsi_temuan }}">{{ $finding->deskripsi_temuan }}</p>
                    </td>
                    <td class="{{ $cellClass }}">
                        <x-main.non-conformities.photo :path="$finding->foto_temuan" :finding-id="$finding->id" field="foto_temuan" :can-upload="$canManage" />
                    </td>
                    <td class="{{ $cellClass }} whitespace-nowrap">{{ $finding->no_klausul ?? '—' }}</td>
                    <td class="{{ $cellClass }}">
                        @if ($finding->kategori)
                            <x-ui.badge size="sm" :color="$categoryColor($finding->kategori)">{{ $finding->kategori }}</x-ui.badge>
                        @else
                            —
                        @endif
                    </td>
                    <td class="{{ $cellClass }} whitespace-nowrap">
                        @if ($finding->picUser)
                            <p class="text-gray-800 dark:text-white/90">{{ $finding->picUser->name }}</p>
                            <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $finding->picUser->email }}</p>
                        @else
                            {{ $finding->pic ?? '—' }}
                        @endif
                    </td>
                    <td class="{{ $cellClass }}">
                        <x-main.non-conformities.photo :path="$finding->foto_perbaikan" :finding-id="$finding->id" field="foto_perbaikan" can-upload />
                    </td>
                    <td class="{{ $cellClass }}">
                        @if ($finding->isClosed())
                            <x-ui.badge size="sm" color="success">{{ __('nc.closed') }}</x-ui.badge>
                        @else
                            <x-ui.badge size="sm" color="warning">{{ __('nc.open') }}</x-ui.badge>
                        @endif
                    </td>
                    @if ($canManage)
                        <td class="{{ $cellClass }}">
                            <div class="flex items-center justify-center gap-1.5">
                                <x-ui.button variant="outline" size="icon-xs" as="a" href="{{ route('non-conformities.edit', $finding) }}"
                                    aria-label="{{ __('clause.edit') }} #{{ $finding->id }}" title="{{ __('clause.edit') }}">
                                    <x-svg.pencil class="size-4" />
                                </x-ui.button>
                                <x-ui.button variant="outline" size="icon-xs" class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10"
                                    x-on:click="confirmDelete(['{{ $finding->id }}'])" aria-label="{{ __('clause.delete') }} #{{ $finding->id }}" title="{{ __('clause.delete') }}">
                                    <x-svg.trash-bin class="size-4" />
                                </x-ui.button>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columns }}" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('nc.empty') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
