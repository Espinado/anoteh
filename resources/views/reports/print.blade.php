<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.reports') }} · {{ __('app.report_types.'.$filters['type']) }}</title>
    <style>
        body { font: 13px/1.45 Arial, sans-serif; color: #172033; margin: 28px; }
        h1 { margin: 0 0 6px; } p { color: #5b6473; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #d9dee7; padding: 8px; text-align: left; }
        th { background: #eef2f7; } .actions { margin-bottom: 20px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">{{ __('app.print') }}</button></div>
    <h1>{{ __('app.report_types.'.$filters['type']) }}</h1>
    <p>{{ __('app.generated_at') }}: {{ now('Europe/Riga')->format('d.m.Y H:i') }}</p>
    <table>
        <thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">{{ __('app.no_records') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
