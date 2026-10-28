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
        time: '--:--',
        date: '',
        timer: null,
        tick() {
            const now = new Date();
            const hour = now.getHours();

            if (hour >= 4 && hour < 11) this.greeting = this.greetings.morning;
            else if (hour >= 11 && hour < 15) this.greeting = this.greetings.afternoon;
            else if (hour >= 15 && hour < 18) this.greeting = this.greetings.evening;
            else this.greeting = this.greetings.night;

            this.time = String(hour).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            this.date = new Intl.DateTimeFormat(document.documentElement.lang, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);

            // re-render right when the next minute starts
            this.timer = setTimeout(() => this.tick(), (60 - now.getSeconds()) * 1000 - now.getMilliseconds());
        },
        destroy() {
            clearTimeout(this.timer);
        },
    }"
    x-init="tick()"
    x-on:visibilitychange.document="if (!document.hidden) { clearTimeout(timer); tick(); }"
    {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3']) }}
>
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">
                <span x-text="greeting"></span>, {{ $name }}
            </h3>
            <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">
                {{ __('dashboard.welcome') }}
            </p>
        </div>

        <div class="text-start sm:text-end">
            <p class="text-title-lg font-bold tabular-nums text-gray-800 dark:text-white/90" x-text="time">--:--</p>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400" x-text="date"></p>
        </div>
    </div>
</div>
