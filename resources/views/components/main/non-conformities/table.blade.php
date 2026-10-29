@props(['findings', 'showPlant' => false])
@php
    $headClass = 'px-4 py-3 text-start text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
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
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($findings as $finding)
                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/2">
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
                        <x-main.non-conformities.photo :path="$finding->foto_temuan" :alt="__('nc.columns.findingPhoto').' #'.$finding->id" />
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
                        <x-main.non-conformities.photo :path="$finding->foto_perbaikan" :alt="__('nc.columns.repairPhoto').' #'.$finding->id" />
                    </td>
                    <td class="{{ $cellClass }}">
                        @if ($finding->isClosed())
                            <x-ui.badge size="sm" color="success">{{ __('nc.closed') }}</x-ui.badge>
                        @else
                            <x-ui.badge size="sm" color="warning">{{ __('nc.open') }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showPlant ? 11 : 10 }}" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('nc.empty') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
