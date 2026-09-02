<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<style>
    @font-face {
        font-family: 'Sarabun';
        font-weight: normal;
        font-style: normal;
        src: url('{{ $regularFontPath }}');
    }
    @font-face {
        font-family: 'Sarabun';
        font-weight: bold;
        font-style: normal;
        src: url('{{ $boldFontPath }}');
    }
    @page { margin: 24px 28px; }
    body {
        font-family: 'Sarabun', sans-serif;
        font-size: 11px;
        color: #111;
    }
    h1 {
        text-align: center;
        font-size: 16px;
        margin: 0 0 2px 0;
    }
    .subtitle {
        text-align: center;
        font-size: 12px;
        color: #444;
        margin: 0 0 12px 0;
    }
    .filters {
        width: 100%;
        margin-bottom: 10px;
        border-collapse: collapse;
    }
    .filters td {
        padding: 2px 6px;
    }
    .filters .label { font-weight: bold; white-space: nowrap; }
    table.data {
        width: 100%;
        border-collapse: collapse;
    }
    table.data th, table.data td {
        border: 1px solid #999;
        padding: 5px 6px;
        text-align: center;
    }
    table.data thead th {
        background: #eee;
        font-weight: bold;
    }
    table.data td.type-name {
        text-align: left;
        font-weight: bold;
    }
    table.data tfoot td {
        background: #f5f5f5;
        font-weight: bold;
    }
    .muted { color: #999; }
    .footer {
        margin-top: 14px;
        font-size: 10px;
        color: #666;
        text-align: right;
    }
</style>
</head>
<body>
    <h1>สรุปผลการประเมินการบริการงานซ่อม IT</h1>
    <p class="subtitle">แยกตามประเภทงานและเกณฑ์การประเมิน (มาตรวัด 5 ดาว — 5 ดีที่สุด)</p>

    <table class="filters">
        <tr>
            <td class="label">ปี:</td>
            <td>{{ $year }}</td>
            <td class="label">เดือน:</td>
            <td>{{ $monthLabel }}</td>
            <td class="label">ประเภทงาน:</td>
            <td>{{ $typeLabel }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="text-align:left;">ประเภทงาน</th>
                @foreach ($criteria as $c)
                    <th>{{ $c->name }}@unless ($c->is_active) <span class="muted">(ปิดใช้งาน)</span>@endunless</th>
                @endforeach
                <th>คะแนนรวมเฉลี่ย</th>
                <th>จำนวนการประเมิน</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="type-name">{{ $row['service_type'] }}</td>
                    @foreach ($criteria as $c)
                        @php ($v = $row['by_criterion'][$c->id] ?? null)
                        <td>{{ $v !== null ? number_format($v, 2) : '—' }}</td>
                    @endforeach
                    <td>{{ $row['overall'] !== null ? number_format($row['overall'], 2) : '—' }}</td>
                    <td>{{ $row['evaluations'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $criteria->count() + 3 }}" class="muted" style="padding:16px;">
                        ยังไม่มีข้อมูลการประเมินตามเงื่อนไขที่เลือก
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (count($rows))
        <tfoot>
            <tr>
                <td style="text-align:left;">รวมทั้งหมด</td>
                @foreach ($criteria as $c)
                    @php ($v = $total['by_criterion'][$c->id] ?? null)
                    <td>{{ $v !== null ? number_format($v, 2) : '—' }}</td>
                @endforeach
                <td>{{ $total['overall'] !== null ? number_format($total['overall'], 2) : '—' }}</td>
                <td>{{ $total['evaluations'] }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <p class="footer">พิมพ์เมื่อ {{ $generatedAt }} น.</p>
</body>
</html>
