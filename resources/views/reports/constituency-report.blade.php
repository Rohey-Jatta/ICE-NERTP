<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $constituency }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2329; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #0e1014; }
        .meta { color: #5f6773; font-size: 10px; margin-bottom: 14px; }
        .brand { color: #e61a6e; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 9px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { border: 1px solid #e6e8ec; padding: 6px 8px; }
        .summary .label { color: #5f6773; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary .value { font-size: 13px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #0e1014; color: #fff; text-align: left; padding: 6px 7px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.data td { border-bottom: 1px solid #e6e8ec; padding: 5px 7px; }
        table.data tr:nth-child(even) td { background: #f8f9fb; }
        .footer { margin-top: 18px; color: #8b95a3; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">IEC — National Election Results Transmission Platform</div>
    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ $constituency }} Constituency · {{ $election }} · Generated {{ $generatedAt }}
    </div>

    <table class="summary">
        <tr>
            @foreach ($summary as $label => $value)
                <td>
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value }}</div>
                </td>
                @if ($loop->iteration % 4 === 0 && !$loop->last)
                    </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_numeric($cell) ? number_format((float) $cell, fmod((float) $cell, 1) ? 2 : 0) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No data available for this report.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Independent Electoral Commission — official constituency report. This document was generated electronically.
    </div>
</body>
</html>
