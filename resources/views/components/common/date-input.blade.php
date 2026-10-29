{{--
    Single flatpickr date input with a clear button. Dispatches `date-change` with { value } (Y-m-d,
    or '' when cleared). `min` / `max` are Alpine expressions of the surrounding component that limit
    the selectable days (e.g. min="dari" on a "to" field), kept in sync while they change.
--}}
@props(['value' => null, 'id' => null, 'name' => null, 'placeholder' => '', 'min' => null, 'max' => null])
<div
    data-date-input
    x-data="{
        value: @js($value ?? ''),
        picker: null,
        init() {
            this.picker = window.flatpickr(this.$refs.input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                defaultDate: this.value || null,
                onChange: (dates) => {
                    this.value = dates[0] ? this.picker.formatDate(dates[0], 'Y-m-d') : '';
                    this.$dispatch('date-change', { value: this.value });
                },
            });
        },
        clear(silent = false) {
            this.picker.clear(false);
            this.value = '';
            if (!silent) this.$dispatch('date-change', { value: '' });
        },
        destroy() {
            this.picker?.destroy();
        },
    }"
    @if ($min) x-effect="picker?.set('minDate', ({{ $min }}) || null)" @endif
    @if ($max) x-effect="picker?.set('maxDate', ({{ $max }}) || null)" @endif
    {{ $attributes->merge(['class' => 'relative']) }}
>
    @if ($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="value" value="{{ $value }}">
    @endif

    <x-ui.input type="text" x-ref="input" :id="$id" placeholder="{{ $placeholder }}" class="cursor-pointer pe-16" readonly />

    <button type="button" x-show="value" x-cloak x-on:click="clear()" aria-label="{{ __('nc.clearDates') }}"
        class="absolute end-10 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300">
        <x-svg.x class="size-4" />
    </button>
    <span class="pointer-events-none absolute end-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
        <x-svg.calendar class="size-5" />
    </span>
</div>
