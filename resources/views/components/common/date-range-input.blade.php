{{--
    One flatpickr input for picking a date range. Submits two hidden Y-m-d fields
    (nameFrom / nameTo); picking a single day sets both to that day. Dispatches
    `date-range-change` with { from, to } whenever the range is set or cleared.
--}}
@props(['nameFrom' => 'from', 'nameTo' => 'to', 'from' => null, 'to' => null, 'placeholder' => '', 'id' => null])
<div
    x-data="{
        from: @js($from ?? ''),
        to: @js($to ?? ''),
        picker: null,
        init() {
            const toYmd = (date) => this.picker.formatDate(date, 'Y-m-d');
            this.picker = window.flatpickr(this.$refs.input, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                defaultDate: [this.from, this.to].filter(Boolean),
                onChange: (dates) => {
                    this.from = dates[0] ? toYmd(dates[0]) : '';
                    this.to = dates[1] ? toYmd(dates[1]) : this.from;
                    // a range is complete with two dates; a single day is completed in onClose
                    if (dates.length === 2) this.notify();
                },
                onClose: (dates) => {
                    if (dates.length === 1) {
                        this.picker.setDate([dates[0], dates[0]], false);
                        this.notify();
                    }
                },
            });
        },
        notify() {
            this.$dispatch('date-range-change', { from: this.from, to: this.to });
        },
        clear(silent = false) {
            this.picker.clear(false);
            this.from = '';
            this.to = '';
            if (!silent) this.notify();
        },
        destroy() {
            this.picker?.destroy();
        },
    }"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <input type="hidden" name="{{ $nameFrom }}" x-bind:value="from" value="{{ $from }}">
    <input type="hidden" name="{{ $nameTo }}" x-bind:value="to" value="{{ $to }}">

    <x-ui.input type="text" x-ref="input" :id="$id" placeholder="{{ $placeholder }}" class="cursor-pointer pe-16" readonly />

    <button type="button" x-show="from" x-cloak x-on:click="clear()" aria-label="{{ __('nc.clearDates') }}"
        class="absolute end-10 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300">
        <x-svg.x class="size-4" />
    </button>
    <span class="pointer-events-none absolute end-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
        <x-svg.calendar class="size-5" />
    </span>
</div>
