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

        .page-break {
            page-break-before: always;
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

        .data-table tbody tr:hover td {
            background: #f1f5ff;
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
                Remote demo environment | Jakarta, Indonesia
            </div>
            <div class="footer-cell footer-right">
                <span class="footer-item">
                    portfolio@example.com
                </span>
                <span class="footer-item">
                    portfolio-demo.example
                </span>
            </div>
        </div>
    </div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img class="logo-img" src="{{ public_path('assets/img/logo.png') }}" alt="Portfolio Logo">
                </td>
                <td class="header-left">
                    Trade Analytics Portfolio<br>
                    <div class="header-title">Portfolio Demo Team</div>
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
        <h1>EVALUASI KINERJA NILAI INVESTASI BILATERAL INDONESIA {{ $periodEnd ?? $latestYear }}</h1>
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
            <div class="stat-label">Total Investasi Masuk ({{ $periodEnd ?? '-' }})</div>
            <div class="stat-value">
                {{ $totalInboundLatest !== null ? number_format($totalInboundLatest, 0, ',', '.') . ' ' . $unit : '-' }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Investasi Keluar ({{ $periodEnd ?? '-' }})</div>
            <div class="stat-value">
                {{ $totalOutboundLatest !== null ? number_format($totalOutboundLatest, 0, ',', '.') . ' ' . $unit : '-' }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Periode</div>
            <div class="stat-value">
                {{ $periodStart && $periodEnd ? $periodStart . ' - ' . $periodEnd : ($periodEnd ?? '-') }}
            </div>
        </div>
    </div>

    @if ($inbound)
        <div class="section-title">I. INVESTASI MASUK KE INDONESIA</div>
        <div class="section-subtitle">
            Ringkasan tren investasi masuk ke Indonesia dan negara asal utama pada tahun {{ $inbound['latestYear'] }}.
        </div>

        <div class="chart-grid">
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Investasi Masuk</div>
                    @if (!empty($inbound['trendLineChart']))
                        <img class="line-chart" src="{{ $inbound['trendLineChart'] }}" alt="Line chart investasi masuk">
                    @else
                        <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                    @endif
                </div>
            </div>
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Top 5 Negara Asal Investasi ({{ $inbound['latestYear'] }})</div>
                    @if (!empty($inbound['top5BarChart']))
                        <img class="line-chart" src="{{ $inbound['top5BarChart'] }}" alt="Top 5 investasi masuk">
                    @else
                        <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="section-title" style="margin-top: 12px;">
            {{ ($inbound['partnerCount'] ?? 0) > 10 ? 'Tabel Top 10 Mitra Investasi Indonesia (Inbound)' : 'Tabel Mitra Investasi Indonesia (Inbound)' }}
        </div>
        <div class="muted">
            @if ($inbound['prevYear'])
                Perbandingan nilai investasi masuk tahun {{ $inbound['latestYear'] }} dengan tahun {{ $inbound['prevYear'] }}.
            @else
                Ringkasan investasi masuk pada tahun {{ $inbound['latestYear'] }}.
            @endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara Asal Investasi</th>
                    <th>Nilai {{ $inbound['latestYear'] }} ({{ $unit }})</th>
                    @if ($inbound['prevYear'])
                        <th>Nilai {{ $inbound['prevYear'] }} ({{ $unit }})</th>
                        <th>Perubahan</th>
                        <th>Perubahan (%)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($inbound['partnerRows'] as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td>{{ $row['negara'] }}</td>
                        <td class="text-right">{{ number_format($row['latest'], 0, ',', '.') }}</td>
                        @if ($inbound['prevYear'])
                            <td class="text-right">{{ $row['prev'] === null ? '-' : number_format($row['prev'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ $row['delta'] === null ? '-' : number_format($row['delta'], 0, ',', '.') }}</td>
                            <td class="text-right">
                                {{ $row['pct'] === null ? '-' : number_format($row['pct'], 2, ',', '.') . '%' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($outbound)
        <div class="section-title" style="margin-top: 16px;">II. INVESTASI KELUAR DARI INDONESIA</div>
        <div class="section-subtitle">
            Ringkasan tren investasi keluar dari Indonesia dan negara tujuan utama pada tahun {{ $outbound['latestYear'] }}.
        </div>

        <div class="chart-grid">
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Investasi Keluar</div>
                    @if (!empty($outbound['trendLineChart']))
                        <img class="line-chart" src="{{ $outbound['trendLineChart'] }}" alt="Line chart investasi keluar">
                    @else
                        <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                    @endif
                </div>
            </div>
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Top 5 Negara Tujuan Investasi ({{ $outbound['latestYear'] }})</div>
                    @if (!empty($outbound['top5BarChart']))
                        <img class="line-chart" src="{{ $outbound['top5BarChart'] }}" alt="Top 5 investasi keluar">
                    @else
                        <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="section-title" style="margin-top: 12px;">
            {{ ($outbound['partnerCount'] ?? 0) > 10 ? 'Tabel Top 10 Mitra Investasi Indonesia (Outbound)' : 'Tabel Mitra Investasi Indonesia (Outbound)' }}
        </div>
        <div class="muted">
            @if ($outbound['prevYear'])
                Perbandingan nilai investasi keluar tahun {{ $outbound['latestYear'] }} dengan tahun {{ $outbound['prevYear'] }}.
            @else
                Ringkasan investasi keluar pada tahun {{ $outbound['latestYear'] }}.
            @endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara Tujuan Investasi</th>
                    <th>Nilai {{ $outbound['latestYear'] }} ({{ $unit }})</th>
                    @if ($outbound['prevYear'])
                        <th>Nilai {{ $outbound['prevYear'] }} ({{ $unit }})</th>
                        <th>Perubahan</th>
                        <th>Perubahan (%)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($outbound['partnerRows'] as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td>{{ $row['negara'] }}</td>
                        <td class="text-right">{{ number_format($row['latest'], 0, ',', '.') }}</td>
                        @if ($outbound['prevYear'])
                            <td class="text-right">{{ $row['prev'] === null ? '-' : number_format($row['prev'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ $row['delta'] === null ? '-' : number_format($row['delta'], 0, ',', '.') }}</td>
                            <td class="text-right">
                                {{ $row['pct'] === null ? '-' : number_format($row['pct'], 2, ',', '.') . '%' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!$inbound && !$outbound)
        <div class="muted">Data investasi tidak tersedia.</div>
    @endif
</body>
</html>
