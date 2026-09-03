<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>
    @font-face {
        font-family: 'Sarabun';
        font-weight: normal;
        src: url('{{ $regularFontPath }}');
    }
    @font-face {
        font-family: 'Sarabun';
        font-weight: bold;
        src: url('{{ $boldFontPath }}');
    }
    @page { margin: 22px 24px; }
    body { font-family: 'Sarabun', sans-serif; font-size: 10px; color: #111; }
    h1 { text-align: center; font-size: 15px; margin: 0 0 2px 0; }
    .subtitle { text-align: center; font-size: 10px; color: #555; margin: 0 0 10px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; vertical-align: top; }
    thead th { background: #eee; font-weight: bold; }
    tbody tr:nth-child(even) { background: #f6f6f6; }
</style>
</head>
<body>
    <h1>ทะเบียนครุภัณฑ์ไอที</h1>
    <p class="subtitle">พิมพ์เมื่อ {{ $generatedAt }} — ทั้งหมด {{ count($rows) }} รายการ</p>
    <table>
        <thead>
            <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
