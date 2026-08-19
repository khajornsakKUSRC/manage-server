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
    @page {
        margin: 22px 28px;
    }
    body {
        font-family: 'Sarabun', sans-serif;
        font-size: 11px;
        color: #111;
    }
    h1 {
        text-align: center;
        font-size: 15px;
        margin: 0 0 2px 0;
    }
    h2 {
        text-align: center;
        font-size: 12px;
        font-weight: bold;
        margin: 0 0 14px 0;
    }
    .meta-row {
        width: 100%;
        margin-bottom: 10px;
    }
    .meta-row td {
        padding: 2px 4px;
        vertical-align: middle;
    }
    .meta-label {
        font-weight: bold;
        white-space: nowrap;
    }
    .pill {
        display: inline-block;
        background: #e5e5e5;
        border-radius: 10px;
        padding: 1px 10px;
    }
    table.data {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    table.data th, table.data td {
        border: 1px solid #999;
        padding: 4px 6px;
        text-align: center;
    }
    table.data thead th {
        background: #f0f0f0;
        font-weight: bold;
    }
    table.data caption {
        border: 1px solid #999;
        background: #f0f0f0;
        font-weight: bold;
        padding: 4px 6px;
        caption-side: top;
    }
    .section-title {
        font-weight: bold;
        margin: 4px 0 6px 0;
    }
    .checklist {
        margin: 0 0 14px 0;
        padding: 0;
    }
    .checklist td {
        padding: 2px 4px;
        vertical-align: middle;
    }
    .box {
        display: inline-block;
        width: 9px;
        height: 9px;
        border: 1px solid #333;
        text-align: center;
        line-height: 9px;
        font-size: 8px;
        margin-right: 6px;
    }
    .note {
        color: #777;
        font-size: 9px;
    }
    .summary-row td {
        padding: 4px 4px;
        vertical-align: middle;
    }
    .reason-line {
        margin-top: 6px;
        border-bottom: 1px solid #999;
        display: inline-block;
        min-width: 380px;
        padding-bottom: 2px;
    }
    .footer {
        margin-top: 24px;
        font-size: 8px;
        color: #999;
        text-align: right;
    }
</style>
</head>
<body>

<h1>รายงานการตรวจสอบระบบ Virtual Machine ประจำวัน</h1>
<h2>Daily VM Health Check Report</h2>

<table class="meta-row">
    <tr>
        <td class="meta-label">ผู้ตรวจสอบ</td>
        <td style="width: 35%;">{{ $inspector ?: '-' }}</td>
        <td class="meta-label">รอบการตรวจ</td>
        <td><span class="pill">Daily</span></td>
        <td style="text-align: right;">วันที่: {{ $reportDate }}</td>
    </tr>
</table>

<table class="data">
    <caption>สรุปสถานะ VM</caption>
    <thead>
        <tr>
            <th>VM ทั้งหมด</th>
            <th>Powered On</th>
            <th>Powered Off</th>
            <th>Availability</th>
            <th>หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $total }} เครื่อง</td>
            <td>{{ $poweredOn }} เครื่อง</td>
            <td>{{ $poweredOff > 0 ? $poweredOff.' เครื่อง' : '-' }}</td>
            <td>{{ $availabilityPct }}</td>
            <td>-</td>
        </tr>
    </tbody>
</table>

<table class="data">
    <caption>ตรวจสอบสถานะ Host</caption>
    <thead>
        <tr>
            <th>Host</th>
            <th>Status</th>
            <th>VM ทั้งหมด (Online)</th>
            <th>ผลตรวจ</th>
            <th>หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($hostRows as $row)
            <tr>
                <td>{{ $row['host'] }}</td>
                <td>-</td>
                <td>{{ $row['total'] }}</td>
                <td>{{ $row['pass'] ? 'ปกติ' : 'พบปัญหา' }}</td>
                <td>-</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">ไม่มีข้อมูล Host</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Checklist ตรวจสอบ VM ประจำวัน</div>
<table class="checklist">
    @foreach ($checklist as $c)
        <tr>
            <td>
                <span class="box">{{ $c['checked'] ? 'v' : '' }}</span>
                {{ $c['label'] }}
                @unless ($c['derivable'])
                    <span class="note">(ไม่มีข้อมูลตรวจสอบอัตโนมัติ)</span>
                @endunless
            </td>
        </tr>
    @endforeach
</table>

<table class="data">
    <caption>VM ที่พบปัญหา</caption>
    <thead>
        <tr>
            <th>Host</th>
            <th>VM Name</th>
            <th>DNS</th>
            <th>Status</th>
            <th>หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($problemVms as $vm)
            <tr>
                <td>{{ $vm['host'] }}</td>
                <td>{{ $vm['name'] }}</td>
                <td>{{ $vm['dns'] }}</td>
                <td>{{ $vm['status'] }}</td>
                <td>{{ $vm['remark'] }}</td>
            </tr>
        @empty
            <tr>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="summary-row">
    <tr>
        <td style="font-weight: bold; white-space: nowrap;">สรุปผลการตรวจสอบ</td>
        <td style="white-space: nowrap;">
            <span class="box">{{ $overallNormal ? 'v' : '' }}</span> ปกติ
        </td>
        <td style="white-space: nowrap;">
            <span class="box">{{ ! $overallNormal ? 'v' : '' }}</span> ผิดปกติ
        </td>
        <td>
            @if (! $overallNormal)
                เพราะ <span class="reason-line">{{ $reasonText }}</span>
            @else
                เพราะ <span class="reason-line">&nbsp;</span>
            @endif
        </td>
    </tr>
</table>

<div class="footer">สร้างรายงานโดยระบบ Manage-Server เมื่อ {{ $generatedAt }}</div>

</body>
</html>
