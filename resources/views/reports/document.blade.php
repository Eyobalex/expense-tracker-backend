<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 18px; }
        table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .context { margin: 0; color: #4b5563; }
    </style>
</head>
<body>
<h1>{{ $document['title'] }}</h1>
@foreach ($document['context'] as $key => $value)
    <p class="context">{{ $key }}: {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</p>
@endforeach
<table>
    <thead><tr>@foreach ($document['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($document['rows'] as $row)
        <tr>@foreach ($document['columns'] as $column)<td>{{ $row[$column] ?? '' }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
