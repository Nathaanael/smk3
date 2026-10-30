{{--
    SMK3 inspection report (dompdf), laid out like the legacy template_pdf.pdf: title, print time and
    logo; "Progress & Kriteria" summary per department; status summary; Non Conformities List with photos.
    dompdf cannot run Tailwind, so this file keeps its own <style>.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('nc.report.title') }}</title>
    <style>
        @page { margin: 32px 34px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #111827; }
        .header { width: 100%; margin-bottom: 14px; }
        .header td { border: 0; padding: 0; vertical-align: top; }
        h1 { font-size: 14px; margin: 4px 0 6px; }
        h2 { font-size: 10px; margin: 16px 0 7px; page-break-after: avoid; }
        .muted { color: #374151; margin: 0 0 2px; }
        .logo { height: 44px; }
        .right { text-align: right; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; font-weight: bold; text-align: left; padding: 6px 5px; border: 1px solid #d9dee5; }
        table.grid td { padding: 5px; border: 1px solid #e2e6eb; vertical-align: middle; word-wrap: break-word; }
        /* many columns: smaller text so the table fits the page width */
        table.dense { font-size: 6.8px; }
        table.dense th, table.dense td { padding: 3px; }
        table.dense .photo { max-width: 80px; max-height: 80px; }
        table.grid tr.total td { background: #f1f5f9; font-weight: bold; }
        table.status { width: 230px; border-collapse: collapse; }
        table.status th { background: #f1f5f9; text-align: left; padding: 4px 5px; border: 1px solid #d9dee5; }
        table.status td { padding: 3px 5px; border: 1px solid #e2e6eb; }
        /* Progress Monitoring: centred numbers, Score / rating / ranking coloured like the page badges */
        table.progress th, table.progress td { text-align: center; }
        table.progress td.name { text-align: left; }
        table.progress td.good { background: #dcfce7; }
        table.progress td.fair { background: #fef3c7; }
        table.progress td.poor { background: #fee2e2; }
        .notes { margin-top: 8px; color: #374151; }
        .notes p { margin: 0 0 3px; }
        .photo { max-width: 100px; max-height: 100px; }
        .nc td { height: 100px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ __('nc.report.title') }}</h1>
                <p class="muted">{{ __('nc.report.printedAt', ['date' => $printedAt->format('d-m-Y H:i')]) }}</p>
                @foreach ($filters as $filter)
                    <p class="muted">{{ $filter }}</p>
                @endforeach
            </td>
            <td class="right">
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="Indofood CBP">
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ __('nc.report.summaryTitle') }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2">{{ __('nc.export.no') }}</th>
                <th rowspan="2">{{ $summaryHeaders['departemen'] }}</th>
                <th colspan="5">{{ __('nc.report.actionProgress') }}</th>
                <th colspan="7">{{ __('nc.report.criteria') }}</th>
                <th rowspan="2">{{ $summaryHeaders['total_score'] }}</th>
            </tr>
            <tr>
                @foreach (['temuan', 'closed', 'open', 'closed_percent', 'closed_score', 'minor', 'major', 'kritis', 'total_nc', 'total_kriteria', 'achievement', 'achievement_score'] as $key)
                    <th>{{ $summaryHeaders[$key] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ([...$summary['rows'], $summary['total']] as $index => $row)
                @php $isTotal = $index === count($summary['rows']); @endphp
                <tr @class(['total' => $isTotal])>
                    <td>{{ $isTotal ? '' : $index + 1 }}</td>
                    <td>{{ $row['departemen'] }}</td>
                    <td>{{ $row['temuan'] }}</td>
                    <td>{{ $row['closed'] }}</td>
                    <td>{{ $row['open'] }}</td>
                    <td>{{ number_format($row['closed_percent'], 2) }}</td>
                    <td>{{ $row['closed_score'] }}</td>
                    <td>{{ $row['minor'] }}</td>
                    <td>{{ $row['major'] }}</td>
                    <td>{{ $row['kritis'] }}</td>
                    <td>{{ $row['total_nc'] }}</td>
                    <td>{{ $row['total_kriteria'] }}</td>
                    <td>{{ number_format($row['achievement'], 2) }}</td>
                    <td>{{ $row['achievement_score'] }}</td>
                    <td>{{ $row['total_score'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>&nbsp;</h2>
    <table class="status">
        <tr><th colspan="2">{{ __('nc.report.summaryStatus') }}</th></tr>
        <tr><td>{{ __('nc.open') }}</td><td>{{ $summary['status']['open'] }}</td></tr>
        <tr><td>{{ __('nc.closed') }}</td><td>{{ $summary['status']['closed'] }}</td></tr>
    </table>

    {{-- Progress Monitoring SMK3 (optional), one table per plant, above the findings list --}}
    @foreach ($progress as $block)
        <h2>{{ __('smk3.progressMonitoring') }} — {{ $block['plant'] }} · {{ $block['period'] }}</h2>
        <table class="grid progress">
            <thead>
                <tr>
                    <th rowspan="2">{{ __('pm.columns.no') }}</th>
                    <th rowspan="2">{{ __('nc.columns.department') }}</th>
                    <th colspan="4">{{ __('pm.groups.progress') }}</th>
                    <th colspan="5">{{ __('pm.groups.deviation') }}</th>
                    <th rowspan="2">{{ __('pm.columns.ranking') }}</th>
                </tr>
                <tr>
                    <th>{{ __('pm.columns.total') }}</th>
                    <th>{{ __('nc.closed') }}</th>
                    <th>{{ __('nc.open') }}</th>
                    <th>{{ __('pm.columns.closedPercent') }}</th>
                    <th>Minor</th>
                    <th>Major</th>
                    <th>Kritis</th>
                    <th>{{ __('pm.columns.score') }}</th>
                    <th>{{ __('pm.columns.rating') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($block['recap']['rows'] as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="name">{{ $row['name'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['closed'] }}</td>
                        <td>{{ $row['open'] }}</td>
                        <td>{{ $row['total'] ? number_format($row['closed_percent'], 2).'%' : '-' }}</td>
                        <td>{{ $row['minor'] }}</td>
                        <td>{{ $row['major'] }}@if ($row['converted_major']) (+{{ $row['converted_major'] }})@endif</td>
                        <td>{{ $row['kritis'] }}</td>
                        <td class="{{ $tone($row['score']) }}">{{ number_format($row['score'], 2) }}%</td>
                        <td class="{{ $row['satisfactory'] ? 'good' : 'poor' }}">{{ $row['satisfactory'] ? __('pm.satisfactory') : __('pm.poor') }}</td>
                        <td class="{{ $tone($row['ranking']) }}">{{ number_format($row['ranking'], 2) }}%</td>
                    </tr>
                @endforeach
                @php $total = $block['recap']['total']; @endphp
                <tr class="total">
                    <td></td>
                    <td class="name">{{ __('pm.total') }}</td>
                    <td>{{ $total['total'] }}</td>
                    <td>{{ $total['closed'] }}</td>
                    <td>{{ $total['open'] }}</td>
                    <td>{{ $total['total'] ? number_format($total['closed_percent'], 2).'%' : '-' }}</td>
                    <td>{{ $total['minor'] }}</td>
                    <td>{{ $total['major'] }}@if ($total['converted_major']) (+{{ $total['converted_major'] }})@endif</td>
                    <td>{{ $total['kritis'] }}</td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>
    @endforeach

    @if ($progressNotes)
        <div class="notes">
            <p><strong>{{ __('pm.export.notes') }}</strong></p>
            @foreach ($progressNotes as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    <h2>{{ __('nc.report.listTitle') }}</h2>
    <table @class(['grid nc', 'dense' => count($columns) > 11])>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $index => $key)
                        <td>
                            @if (in_array($key, ['foto_temuan', 'foto_perbaikan'], true))
                                @if ($row[$index])<img src="{{ $row[$index] }}" class="photo" alt="">@else - @endif
                            @else
                                {{ $row[$index] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">{{ __('nc.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
