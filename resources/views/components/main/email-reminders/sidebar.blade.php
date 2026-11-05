{{--
    Sidebar of the email reminder page: the tabs (write a message and the mail folders). Uses the
    surrounding x-data's "folder".
--}}
@props(['folders' => []])
<aside {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/3']) }}>
    <h3 class="px-1 text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('reminder.title') }}</h3>
    <p class="mt-1 mb-4 px-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ __('reminder.description') }}</p>

    <nav aria-label="{{ __('reminder.title') }}">
        <ul class="flex flex-col gap-1">
            @foreach ($folders as $key => $folder)
                <li>
                    <button type="button" x-on:click="folder = @js($key)"
                        x-bind:aria-current="folder === @js($key) ? 'true' : null"
                        x-bind:class="folder === @js($key)
                            ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5'"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-start text-sm font-medium transition-colors">
                        <x-dynamic-component :component="'svg.'.$folder['icon']" class="size-5 shrink-0" />
                        {{ $folder['label'] }}
                    </button>
                </li>
            @endforeach
        </ul>
    </nav>
</aside>
