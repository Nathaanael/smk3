@extends('layouts.app')

@php
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
@endphp

@section('content')
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3"
        x-data="progressMonitor({
            url: @js(route('progress-monitoring')),
            filters: @js($filters),
            currentMonth: @js($currentMonth),
        })">
        <div class="flex flex-col gap-1 px-5 pt-5 sm:px-6 sm:pt-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('smk3.progressMonitoring') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('smk3.progressMonitoringDesc') }}</p>
        </div>

        {{-- plant + period; every change reloads the results below --}}
        <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2 sm:px-6 lg:flex lg:items-end" x-ref="filters">
            @if (count($plants) > 1)
                <div class="lg:w-64">
                    <label for="pm-pabrik" class="{{ $labelClass }}">{{ __('nc.plant') }}</label>
                    <x-ui.native-select id="pm-pabrik" x-model="pabrik" x-on:change="load()">
                        @foreach ($plants as $code => $name)
                            <option value="{{ $code }}" @selected($filters['pabrik'] === $code)>{{ $name }}</option>
                        @endforeach
                    </x-ui.native-select>
                </div>
            @endif

            <div class="lg:w-52" x-on:date-change="dari = $event.detail.value; load()">
                <label for="pm-dari" class="{{ $labelClass }}">{{ __('nc.dateFrom') }}</label>
                <x-common.date-input id="pm-dari" :value="$filters['dari']" :placeholder="__('nc.anyDate')" max="sampai" />
            </div>
            <div class="lg:w-52" x-on:date-change="sampai = $event.detail.value; load()">
                <label for="pm-sampai" class="{{ $labelClass }}">{{ __('nc.dateTo') }}</label>
                <x-common.date-input id="pm-sampai" :value="$filters['sampai']" :placeholder="__('nc.anyDate')" min="dari" />
            </div>

            <x-ui.button variant="outline" class="h-11" x-on:click="thisMonth()" x-bind:disabled="isThisMonth">
                <x-svg.calendar class="size-5" />
                {{ __('pm.thisMonth') }}
            </x-ui.button>
        </div>

        <div x-ref="results" x-bind:aria-busy="loading" x-bind:class="loading && 'pointer-events-none opacity-60'" class="transition-opacity">
            @fragment('results')
                @php
                    $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M Y') : null;
                    $from = $formatDate($filters['dari']);
                    $to = $formatDate($filters['sampai']);
                @endphp
                <p class="border-t border-gray-100 px-5 py-3 text-sm text-gray-500 sm:px-6 dark:border-gray-800 dark:text-gray-400">
                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $plants[$filters['pabrik']] ?? '—' }}</span>
                    ·
                    @if ($from && $to)
                        {{ __('pm.period', ['from' => $from, 'to' => $to]) }}
                    @elseif ($from)
                        {{ __('pm.since', ['date' => $from]) }}
                    @elseif ($to)
                        {{ __('pm.until', ['date' => $to]) }}
                    @else
                        {{ __('pm.allDates') }}
                    @endif
                </p>

                <x-main.progress-monitoring.table :rows="$recap['rows']" :total="$recap['total']" />
            @endfragment
        </div>
    </div>

    <x-main.progress-monitoring.scoring-guide class="mt-6" :criteria="$recap['criteria'] ?: 166" />
@endsection
