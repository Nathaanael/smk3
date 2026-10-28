<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3']) }}>
    <div class="custom-calendar relative">
        <div id="calendar" class="gap-0!"></div>
    </div>

    <x-main.calendar.event-modal />
</div>
