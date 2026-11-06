{{-- Email clients ignore the site's stylesheet, so this template uses inline styles and fixed colours. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
</head>
<body style="margin: 0; padding: 16px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1d2939; background: #ffffff;">
    <p style="margin: 0 0 20px;">{!! $htmlBody !!}</p>

    <p style="margin: 0 0 8px; font-weight: bold;">{{ __('reminder.email.findings') }}</p>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d0d5dd; font-size: 13px;">
        <thead>
            <tr style="background: #f2f4f7; text-align: left;">
                <th>{{ __('nc.columns.id') }}</th>
                <th>{{ __('nc.columns.date') }}</th>
                <th>{{ __('nc.columns.department') }}</th>
                <th>{{ __('nc.columns.finding') }}</th>
                <th>{{ __('nc.columns.category') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($findings as $finding)
                <tr>
                    <td style="white-space: nowrap;">#{{ $finding->id }}</td>
                    <td style="white-space: nowrap;">{{ $finding->tanggal->translatedFormat('d M Y') }}</td>
                    <td>{{ $finding->departmentName() }}</td>
                    <td>{{ $finding->deskripsi_temuan }}</td>
                    <td>{{ $finding->kategori ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
