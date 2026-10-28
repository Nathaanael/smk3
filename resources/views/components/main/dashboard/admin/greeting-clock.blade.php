@props(['name' => ''])

<div
    x-data="{
        greetings: @js([
            'morning' => __('dashboard.greetingMorning'),
            'afternoon' => __('dashboard.greetingAfternoon'),
            'evening' => __('dashboard.greetingEvening'),
            'night' => __('dashboard.greetingNight'),
        ]),
        greeting: '',
        isNight: false,
        time: '--:--',
        date: '',
        zone: '',
        timer: null,
        tick() {
            const now = new Date();
            const hour = now.getHours();
            const locale = document.documentElement.lang;

            if (hour >= 4 && hour < 11) this.greeting = this.greetings.morning;
            else if (hour >= 11 && hour < 15) this.greeting = this.greetings.afternoon;
            else if (hour >= 15 && hour < 18) this.greeting = this.greetings.evening;
            else this.greeting = this.greetings.night;
            this.isNight = hour < 4 || hour >= 18;

            this.time = String(hour).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            this.date = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
            this.zone = new Intl.DateTimeFormat(locale, { timeZoneName: 'short' }).formatToParts(now).find((part) => part.type === 'timeZoneName')?.value ?? '';

            // re-render right when the next minute starts
            this.timer = setTimeout(() => this.tick(), (60 - now.getSeconds()) * 1000 - now.getMilliseconds());
        },
        destroy() {
            clearTimeout(this.timer);
        },
    }"
    x-init="tick()"
    x-on:visibilitychange.document="if (!document.hidden) { clearTimeout(timer); tick(); }"
    {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3']) }}
>
    {{-- decoration --}}
    <img src="/assets/images/shape/grid-01.svg" alt="" aria-hidden="true"
        class="pointer-events-none absolute end-0 top-0 -z-1 w-full max-w-90 opacity-50 select-none dark:opacity-20" />

    <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-theme-xs font-medium text-gray-600 dark:border-gray-800 dark:bg-white/3 dark:text-gray-300">
                <x-svg.sun class="size-4 text-gray-500 dark:text-gray-400" x-show="!isNight" />
                <x-svg.moon class="size-4 text-gray-500 dark:text-gray-400" x-show="isNight" x-cloak />
                <span x-text="date"></span>
            </span>

            <h2 class="mt-5 text-title-sm font-semibold tracking-tight text-gray-800 md:text-title-md dark:text-white/90">
                <span class="block text-xl font-medium text-gray-500 md:text-2xl dark:text-gray-400" x-text="greeting + ','"></span>
                <span class="mt-1 block break-words">{{ $name }}</span>
            </h2>

            <p class="mt-3 max-w-xl text-theme-sm text-gray-500 md:text-base dark:text-gray-400">
                {{ __('dashboard.welcome') }}
            </p>
        </div>

        <div class="shrink-0 rounded-2xl border border-gray-200 bg-gray-50 px-6 py-5 lg:min-w-56 dark:border-gray-800 dark:bg-white/3 lg:text-end">
            <div class="flex items-baseline gap-2 lg:justify-end">
                <span class="text-title-md leading-none font-semibold tracking-tight text-gray-800 tabular-nums md:text-title-lg dark:text-white/90" x-text="time">--:--</span>
                <span class="text-theme-sm font-medium text-gray-500 dark:text-gray-400" x-text="zone"></span>
            </div>
        </div>
    </div>
</div>
