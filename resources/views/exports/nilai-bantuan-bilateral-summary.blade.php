<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 70px 40px 95px 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
        }

        .header {
            width: 100%;
            margin-bottom: 8px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 64px;
            vertical-align: top;
        }

        .logo-img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .header-left {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .header-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }

        .header-subtitle {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
            margin-top: 2px;
        }

        .header-right {
            text-align: right;
            font-size: 10px;
            color: #475569;
            vertical-align: top;
        }

        .divider {
            height: 2px;
            background: #ef4444;
            margin: 6px 0 2px 0;
        }

        .divider-accent {
            height: 2px;
            background: #1d4ed8;
            margin: 2px 0 12px 0;
        }

        .title-block {
            text-align: center;
            margin: 14px 0 10px;
        }

        .title-block h1 {
            font-size: 15px;
            margin: 0;
            letter-spacing: 0.4px;
        }

        .title-block p {
            margin: 4px 0 0 0;
            font-size: 11px;
            color: #475569;
            font-style: italic;
        }

        .summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 11px;
            line-height: 1.45;
            text-align: justify;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 16px 0 6px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .section-subtitle {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .stats-grid {
            margin-top: 10px;
            display: table;
            width: 100%;
            border-spacing: 6px;
        }

        .stat-card {
            display: table-cell;
            width: 33%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            vertical-align: top;
        }

        .stat-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.6px;
        }

        .stat-value {
            font-size: 13px;
            font-weight: bold;
            margin-top: 6px;
            color: #0f172a;
        }

        .chart-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 10px;
        }

        .chart-grid {
            display: table;
            width: 100%;
            border-spacing: 10px 0;
            margin-top: 6px;
        }

        .chart-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .chart-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .line-chart {
            width: 100%;
            height: 160px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: auto;
        }

        .data-table th, .data-table td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            font-size: 9px;
        }

        .data-table th {
            background: #f8fafc;
            text-align: center;
            font-weight: bold;
        }

        .data-table td {
            vertical-align: top;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #fcfdff;
        }

        .header-table td {
            border: none;
            padding: 0;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .footer {
            position: fixed;
            bottom: -12px;
            left: 40px;
            right: 40px;
            font-size: 9px;
            color: #6b7280;
            z-index: 10;
        }

        .footer-line {
            border-top: 1px solid #e2e8f0;
            margin-bottom: 8px;
        }

        .footer-row {
            display: table;
            width: 100%;
        }

        .footer-cell {
            display: table-cell;
            vertical-align: middle;
            font-size: 9px;
            color: #64748b;
        }

        .footer-right {
            text-align: right;
        }

        .footer-item {
            display: inline-block;
            margin-left: 14px;
        }

        .col-no {
            width: 28px;
        }
    </style>
</head>
<body>
    <div class="footer">
        <div class="footer-line"></div>
        <div class="footer-row">
            <div class="footer-cell">
                Jl. Taman Pejambon No.6, Senen, Jakarta Pusat, DKI Jakarta, 10410
            </div>
            <div class="footer-cell footer-right">
                <span class="footer-item">
                    data1.pskikad@kemlu.go.id
                </span>
                <span class="footer-item">
                    side.kemlu.go.id
                </span>
            </div>
        </div>
    </div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img class="logo-img" src="{{ public_path('assets/img/logo-kemlu.png') }}" alt="Logo Kemlu">
                </td>
                <td class="header-left">
                    Kementerian Luar Negeri Republik Indonesia<br>
                    <div class="header-title">Badan Strategi Kebijakan Luar Negeri (BSKLN)</div>
                    <div class="header-subtitle">Data Summary</div>
                </td>
                <td class="header-right">
                    Tanggal: {{ $tanggalCetak }}<br>
                    SIDE (Sistem Informasi Diplomasi Ekonomi)<br>
                    Sumber data: {{ $sourceName }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>EVALUASI KINERJA NILAI BANTUAN BILATERAL INDONESIA {{ $latestYear ?? '-' }}</h1>
        @if (!empty($partnerHeadline))
            <p class="muted" style="margin-top: 4px;">{{ $partnerHeadline }}</p>
        @endif
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">
        {{ $summaryNarrative }}
    </div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Bantuan ({{ $latestYear ?? '-' }})</div>
            <div class="stat-value">
                {{ $totalLatest !== null ? number_format($totalLatest, 2, ',', '.') . ' ' . $unit : '-' }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Periode</div>
            <div class="stat-value">
                {{ $prevYear && $latestYear ? $prevYear . ' - ' . $latestYear : ($latestYear ?? '-') }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Sumber Data</div>
            <div class="stat-value">
                {{ $sourceName ?? '-' }}
            </div>
        </div>
    </div>

    <div class="section-title">BANTUAN BILATERAL</div>
    <div class="section-subtitle">
        Ringkasan tren bantuan dan negara mitra utama pada tahun {{ $latestYear ?? '-' }}.
    </div>

    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Bantuan</div>
                @if (!empty($trendLineChart))
                    <img class="line-chart" src="{{ $trendLineChart }}" alt="Line chart bantuan">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Top 5 Negara Mitra ({{ $latestYear ?? '-' }})</div>
                @if (!empty($top5BarChart))
                    <img class="line-chart" src="{{ $top5BarChart }}" alt="Top 5 negara mitra bantuan">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
    </div>

    <div class="section-title" style="margin-top: 12px;">
        {{ ($partnerCount ?? 0) > 10 ? 'Tabel Top 10 Mitra Bantuan Indonesia' : 'Tabel Mitra Bantuan Indonesia' }}
    </div>
    <div class="muted">
        @if ($prevYear)
            Perbandingan nilai bantuan tahun {{ $latestYear }} dengan tahun {{ $prevYear }}.
        @else
            Ringkasan bantuan pada tahun {{ $latestYear }}.
        @endif
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Negara Mitra</th>
                <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                @if ($prevYear)
                    <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($partnerRows as $i => $row)
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td>{{ $row['negara'] }}</td>
                    <td class="text-right">{{ number_format($row['latest'], 2, ',', '.') }}</td>
                    @if ($prevYear)
                        <td class="text-right">{{ $row['prev'] === null ? '-' : number_format($row['prev'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ $row['delta'] === null ? '-' : number_format($row['delta'], 2, ',', '.') }}</td>
                        <td class="text-right">
                            {{ $row['pct'] === null ? '-' : number_format($row['pct'], 2, ',', '.') . '%' }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (!empty($kawasanRows))
        <div class="section-title" style="margin-top: 16px;">Top Kawasan Bantuan</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Kawasan</th>
                    <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                    @if ($prevYear)
                        <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                        <th>Perubahan</th>
                        <th>Perubahan (%)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($kawasanRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td>{{ $row['kawasan'] }}</td>
                        <td class="text-right">{{ number_format($row['latest'], 2, ',', '.') }}</td>
                        @if ($prevYear)
                            <td class="text-right">{{ $row['prev'] === null ? '-' : number_format($row['prev'], 2, ',', '.') }}</td>
                            <td class="text-right">{{ $row['delta'] === null ? '-' : number_format($row['delta'], 2, ',', '.') }}</td>
                            <td class="text-right">
                                {{ $row['pct'] === null ? '-' : number_format($row['pct'], 2, ',', '.') . '%' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
