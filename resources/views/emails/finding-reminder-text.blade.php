{!! $textBody !!}

{!! __('reminder.email.findings') !!}
@foreach ($findings as $finding)
- #{{ $finding->id }} | {{ $finding->tanggal->translatedFormat('d M Y') }} | {!! $finding->departmentName() !!} | {!! $finding->deskripsi_temuan !!} | {!! $finding->kategori ?? '—' !!}
@endforeach
