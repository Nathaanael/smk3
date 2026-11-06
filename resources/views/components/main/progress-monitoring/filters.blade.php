{{--
    Plant + period filter row; every change reloads the results.
    Must sit inside x-data="progressMonitor(...)" or a component built on it.
--}}
@props(['plants' => [], 'filters' => []])
@php
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:flex lg:items-end']) }} x-ref="filters">
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
