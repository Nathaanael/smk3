{{--
    The numbers behind a dashboard chart, opened from its card. The grid stays put, and on phones it
    rises from the bottom edge as a sheet.
    A table cell is plain text, or an array: ['text', 'badge' => colour], ['text', 'bar' => 0-100] for
    a small bar under the value, or in the first column ['text', 'description'] for a line of detail.
--}}
@props(['title', 'table', 'period' => []])
@php
    $headClass = 'px-5 py-3 text-xs font-medium whitespace-nowrap text-gray-500 sm:px-6 dark:text-gray-400';
@endphp
<x-ui.dialog>
    <x-ui.button variant="outline" size="xs" class="mt-3" x-on:click="show()">
        <x-svg.table class="size-4" />
        {{ __('dashboard.showTable') }}
    </x-ui.button>

    <x-ui.dialog.content class="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 max-sm:start-0 max-sm:top-auto max-sm:bottom-0 max-sm:max-w-full max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-b-none sm:max-w-3xl">
        <x-ui.dialog.header class="relative isolate shrink-0 gap-3 overflow-hidden border-b border-gray-100 bg-linear-to-br from-brand-50 to-white p-5 pe-14 sm:p-6 sm:pe-14 dark:border-white/5 dark:from-brand-500/20 dark:to-transparent">
            <img src="{{ asset('assets/images/shape/grid-01.svg') }}" alt="" aria-hidden="true"
                class="pointer-events-none absolute end-0 top-0 -z-1 w-full max-w-72 opacity-50 select-none dark:opacity-20" />

            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white shadow-theme-xs" aria-hidden="true">
                    <x-svg.table class="size-5" />
                </span>
                <div class="min-w-0">
                    <x-ui.dialog.title>{{ $title }}</x-ui.dialog.title>
                    <x-ui.dialog.description>
                        <x-main.progress-monitoring.period-label class="mt-0.5" :plant="$period['plant'] ?? null" :from="$period['from'] ?? null" :to="$period['to'] ?? null" />
                    </x-ui.dialog.description>
                </div>
            </div>
        </x-ui.dialog.header>

        <div class="custom-scrollbar min-h-0 flex-1 overflow-auto">
            <table class="min-w-full">
                <thead class="sticky top-0 z-1 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        @foreach ($table['headers'] as $header)
                            <th scope="col" class="{{ $headClass }} {{ $loop->first ? 'text-start' : 'text-end' }}">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($table['rows'] as $row)
                        <tr class="transition hover:bg-gray-50/50 dark:hover:bg-white/2">
                            @foreach ($row as $cell)
                                @php
                                    $text = is_array($cell) ? $cell['text'] : $cell;
                                    // zeros recede, so the counts that need attention stand out
                                    $tone = $text === 0 ? 'text-gray-300 dark:text-gray-600' : 'text-gray-500 dark:text-gray-400';
                                @endphp
                                @if ($loop->first)
                                    <td class="px-5 py-3.5 text-start text-sm font-medium text-gray-800 sm:px-6 dark:text-white/90">
                                        {{ $text }}
                                        @if (filled($cell['description'] ?? null))
                                            <span class="mt-0.5 block min-w-56 text-theme-xs font-normal text-gray-500 dark:text-gray-400">{{ $cell['description'] }}</span>
                                        @endif
                                    </td>
                                @else
                                    <td class="px-5 py-3.5 text-end text-sm whitespace-nowrap tabular-nums sm:px-6 {{ $tone }}">
                                        @if (isset($cell['badge']))
                                            <x-ui.badge size="sm" :color="$cell['badge']">{{ $text }}</x-ui.badge>
                                        @elseif (isset($cell['bar']))
                                            <div class="ms-auto flex w-24 flex-col items-end gap-1.5">
                                                <span class="font-medium {{ $text === 0 ? '' : 'text-gray-800 dark:text-white/90' }}">{{ $text }}</span>
                                                <div class="h-1 w-full rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                                    {{-- width from data: a 0–100 percentage --}}
                                                    <div class="h-full rounded-full bg-brand-500 dark:bg-brand-400" style="width: {{ min(100, $cell['bar']) }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            {{ $text }}
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-gray-100 px-5 py-3 sm:px-6 dark:border-white/5">
            <span class="text-theme-xs text-gray-500 dark:text-gray-400">{{ __('dashboard.tableRows', ['count' => count($table['rows'])]) }}</span>
            <x-ui.dialog.close as-child>
                <x-ui.button variant="outline" size="sm">{{ __('dashboard.closeTable') }}</x-ui.button>
            </x-ui.dialog.close>
        </div>
    </x-ui.dialog.content>
</x-ui.dialog>
