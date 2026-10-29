@extends('layouts.app')

@section('content')
    <x-common.component-card :title="__('smk3.progressMonitoring')" :desc="__('smk3.progressMonitoringDesc')">
        <p class="py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
            {{ __('smk3.comingSoon') }}
        </p>
    </x-common.component-card>
@endsection
