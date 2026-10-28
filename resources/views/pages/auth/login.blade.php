@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4 py-10 sm:px-6 dark:bg-gray-950">
        <div class="shadow-theme-lg flex w-full max-w-5xl overflow-hidden rounded-3xl bg-white lg:min-h-150 dark:bg-gray-900">
            <!-- Form -->
            <div class="flex w-full flex-col items-center justify-center px-6 py-12 sm:px-12 lg:w-1/2">
                <div class="w-full max-w-sm text-center">
                    <div class="mb-8 flex flex-col items-center gap-2">
                        <x-common.app-logo :width="220" />
                        <span class="text-brand-500 dark:text-brand-400 text-sm font-semibold tracking-wide">{{ __('login.appName') }}</span>
                    </div>

                    <h1 class="text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                        {{ __('login.title') }}
                    </h1>
                    <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('login.subtitle') }}
                    </p>

                    <form method="POST" action="{{ route('login') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4 text-start">
                        @csrf

                        <div>
                            <label for="email" class="sr-only">{{ __('login.email') }}</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                placeholder="{{ __('login.emailPlaceholder') }}"
                                @class([
                                    'h-12 w-full rounded-full border bg-transparent px-5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
                                    'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800' => ! $errors->has('email'),
                                    'border-error-500 focus:border-error-500 focus:ring-error-500/10 dark:border-error-500' => $errors->has('email'),
                                ])
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror />
                            @error('email')
                                <p id="email-error" class="text-error-500 mt-1.5 ps-5 text-xs">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ showPassword: false }">
                            <label for="password" class="sr-only">{{ __('login.password') }}</label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" type="password" id="password" name="password" required autocomplete="current-password"
                                    placeholder="{{ __('login.passwordPlaceholder') }}"
                                    class="focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-12 w-full rounded-full border border-gray-300 bg-transparent ps-5 pe-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                <button type="button" @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? @js(__('login.hidePassword')) : @js(__('login.showPassword'))"
                                    class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                    <x-svg.eye x-show="!showPassword" class="size-5" />
                                    <x-svg.eye-close x-show="showPassword" x-cloak class="size-5" />
                                </button>
                            </div>
                            @error('password')
                                <p class="text-error-500 mt-1.5 ps-5 text-xs">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-center pt-4">
                            <button type="submit" :disabled="submitting"
                                class="bg-brand-500 hover:bg-brand-600 shadow-theme-xs focus-visible:ring-brand-500/30 h-12 w-full max-w-56 rounded-full text-sm font-medium tracking-wider text-white uppercase transition focus-visible:ring-4 focus-visible:outline-hidden disabled:cursor-wait disabled:opacity-70">
                                {{ __('login.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Welcome panel -->
            <div class="bg-brand-500 hidden w-1/2 items-center justify-center rounded-s-[10rem] px-12 lg:flex xl:rounded-s-[13rem] dark:bg-brand-700">
                <div class="max-w-sm text-center">
                    <h2 class="text-title-md mb-5 font-semibold text-white">
                        {{ __('login.welcomeTitle') }}
                    </h2>
                    <p class="text-lg leading-relaxed text-white/80">
                        {{ __('login.welcomeText') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
