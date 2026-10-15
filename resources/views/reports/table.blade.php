<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #0f172a; font-size: 16px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #312e81; color: white; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Dicetak {{ now()->format('d/m/Y H:i') }} · {{ $rows->count() }} data</div>
    <table>
        <thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach($rows as $row)
                <tr>@foreach($columns as $column)<td>{{ $row->{$column} ?? '' }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
