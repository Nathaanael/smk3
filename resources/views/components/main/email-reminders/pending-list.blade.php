{{--
    "Needs reminder" tab of the email reminder page: the findings still waiting for a repair photo
    and who they would be sent to. A PIC without an account has no email address to send to, so
    those rows are flagged.
--}}
@props(['findings', 'recipients' => 0, 'filtered' => false, 'showPlant' => false])
@php
    $headClass = 'px-4 py-3 text-start text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $cellClass = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes }}>
    @if ($findings->total() === 0)
        <div class="flex flex-col items-center gap-3 px-5 py-16 text-center sm:px-6">
            <span class="flex size-12 items-center justify-center rounded-full bg-success-50 text-success-500 dark:bg-success-500/15">
                <x-svg.check-circle class="size-6" />
            </span>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __($filtered ? 'nc.empty' : 'reminder.pending.empty') }}</p>
        </div>
    @else
        <p class="px-5 py-4 text-sm text-gray-700 sm:px-6 dark:text-gray-300">
            {{ __('reminder.pending.summary', ['findings' => $findings->total(), 'recipients' => $recipients]) }}
        </p>

        <div class="table-scrollbar max-h-[70vh] max-w-full overflow-auto">
            <table class="min-w-full">
                <thead class="sticky top-0 z-1 border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $headClass }}">{{ __('nc.columns.id') }}</th>
                        <th class="{{ $headClass }}">{{ __('nc.columns.date') }}</th>
                        @if ($showPlant)
                            <th class="{{ $headClass }}">{{ __('nc.columns.plant') }}</th>
                        @endif
                        <th class="{{ $headClass }}">{{ __('nc.columns.department') }}</th>
                        <th class="{{ $headClass }} min-w-72">{{ __('nc.columns.finding') }}</th>
                        <th class="{{ $headClass }}">{{ __('nc.columns.category') }}</th>
                        <th class="{{ $headClass }}">{{ __('nc.columns.pic') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($findings as $finding)
                        <tr>
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
                                @if ($finding->kategori)
                                    <x-ui.badge size="sm" :color="$finding->categoryColor()">{{ $finding->kategori }}</x-ui.badge>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="{{ $cellClass }} whitespace-nowrap">
                                @if ($finding->picUser)
                                    <p class="text-gray-800 dark:text-white/90">{{ $finding->picUser->name }}</p>
                                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $finding->picUser->email }}</p>
                                @else
                                    <p class="mb-1">{{ $finding->pic ?? '—' }}</p>
                                    <x-ui.badge size="sm" color="warning">{{ __('reminder.pending.noAccount') }}</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('nc.showing', ['from' => $findings->firstItem() ?? 0, 'to' => $findings->lastItem() ?? 0, 'total' => $findings->total()]) }}
            </span>
            <x-common.pagination :paginator="$findings" />
        </div>
    @endif
</div>
