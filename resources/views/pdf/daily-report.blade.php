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
        margin: 20px 26px;
    }
    body {
        font-family: 'Sarabun', sans-serif;
        font-size: 10px;
        color: #111;
    }
    .page {
        page-break-after: always;
    }
    .page:last-child {
        page-break-after: auto;
    }
    h1 {
        text-align: center;
        font-size: 14px;
        margin: 0 0 2px 0;
    }
    h2 {
        text-align: center;
        font-size: 11px;
        font-weight: bold;
        margin: 0 0 10px 0;
    }
    .meta-row {
        width: 100%;
        margin-bottom: 8px;
    }
    .meta-row td {
        padding: 2px 4px;
        vertical-align: middle;
    }
    .meta-label {
        font-weight: bold;
        white-space: nowrap;
    }
    table.data {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    table.data th, table.data td {
        border: 1px solid #999;
        padding: 3px 5px;
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
        padding: 3px 6px;
        caption-side: top;
        text-align: left;
    }
    table.vms th, table.vms td {
        font-size: 8.5px;
        padding: 2px 4px;
    }
    table.vms td.name {
        text-align: left;
    }
    .status-up {
        color: #15803d;
        font-weight: bold;
    }
    .status-down {
        color: #b91c1c;
        font-weight: bold;
    }
    .disk-warning {
        color: #b45309;
        font-weight: bold;
    }
    .disk-critical {
        color: #b91c1c;
        font-weight: bold;
    }
    .footer {
        margin-top: 10px;
        font-size: 8px;
        color: #999;
        text-align: right;
    }
</style>
</head>
<body>

@foreach ($pages as $page)
<div class="page">
    <h1>รายงานการตรวจสอบระบบ Virtual Machine ประจำวัน</h1>
    <h2>Daily VM Health Check Report</h2>

    <table class="meta-row">
        <tr>
            <td class="meta-label">วันที่</td>
            <td>{{ $page['reportDate'] }}</td>
            <td class="meta-label">บันทึกโดย</td>
            <td>{{ $page['createdBy'] ?: '-' }}</td>
        </tr>
    </table>

    <table class="data">
        <caption>สรุปสถานะ VM</caption>
        <thead>
            <tr>
                <th>VM ทั้งหมด</th>
                <th>UP</th>
                <th>DOWN</th>
                <th>Availability</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $page['total'] }} เครื่อง</td>
                <td>{{ $page['up'] }} เครื่อง</td>
                <td>{{ $page['down'] }} เครื่อง</td>
                <td>{{ $page['availabilityPct'] }}%</td>
            </tr>
        </tbody>
    </table>

    <table class="data vms">
        <caption>ข้อมูล VM (ดึงอัตโนมัติจาก vCenter)</caption>
        <thead>
            <tr>
                <th>VM Name</th>
                <th>Host</th>
                <th>Status</th>
                <th>CPU</th>
                <th>RAM</th>
                <th>Disk Usage</th>
                <th>Uptime</th>
                <th>Certificate Exp</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($page['rows'] as $row)
                <tr>
                    <td class="name">{{ $row['name'] }}</td>
                    <td>{{ $row['host'] }}</td>
                    <td class="{{ $row['up'] ? 'status-up' : 'status-down' }}">{{ $row['up'] ? 'UP' : 'DOWN' }}</td>
                    <td>{{ $row['cpu_count'] !== null ? $row['cpu_count'].' vCPU' : '-' }}</td>
                    <td>{{ $row['memory_gb'] !== null ? $row['memory_gb'].' GB' : '-' }}</td>
                    <td class="{{ $row['disk_level'] === 'critical' ? 'disk-critical' : ($row['disk_level'] === 'warning' ? 'disk-warning' : '') }}">
                        {{ $row['disk_usage_pct'] !== null ? $row['disk_usage_pct'].'%' : 'N/A' }}
                    </td>
                    <td>{{ $row['uptime'] }}</td>
                    <td>{{ $row['certificate_exp'] }}</td>
                    <td class="name">{{ $row['notes'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">ไม่มีข้อมูล VM</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="data">
        <caption>บันทึกเหตุการณ์ประจำวัน</caption>
        <thead>
            <tr>
                <th>VM</th>
                <th>Incident</th>
                <th>Action</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($page['incidents'] as $incident)
                <tr>
                    <td class="name">{{ $incident['vm_name'] }}</td>
                    <td>{{ $incident['incident'] }}</td>
                    <td>{{ $incident['action'] }}</td>
                    <td class="name">{{ $incident['remark'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">ไม่มีเหตุการณ์ที่บันทึก</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">สร้างรายงานโดยระบบ Manage-Server เมื่อ {{ $generatedAt }}</div>
</div>
@endforeach

</body>
</html>
