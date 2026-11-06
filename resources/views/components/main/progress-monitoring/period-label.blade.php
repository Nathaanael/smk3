{{-- "Plant · period" line of the current filters. --}}
@props(['plant' => null, 'from' => null, 'to' => null])
@php
    $formatDate = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M Y') : null;
    $fromLabel = $formatDate($from);
    $toLabel = $formatDate($to);
@endphp
<p {{ $attributes->merge(['class' => 'text-sm text-gray-500 dark:text-gray-400']) }}>
    <span class="font-medium text-gray-800 dark:text-white/90">{{ $plant ?? '—' }}</span>
    ·
    @if ($fromLabel && $toLabel)
        {{ __('pm.period', ['from' => $fromLabel, 'to' => $toLabel]) }}
    @elseif ($fromLabel)
        {{ __('pm.since', ['date' => $fromLabel]) }}
    @elseif ($toLabel)
        {{ __('pm.until', ['date' => $toLabel]) }}
    @else
        {{ __('pm.allDates') }}
    @endif
</p>
