<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 70px 40px 95px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        .header { width: 100%; margin-bottom: 8px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .logo-cell { width: 64px; vertical-align: top; }
        .logo-img { width: 56px; height: 56px; object-fit: contain; }
        .header-left { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.6px; }
        .header-title { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 4px; }
        .header-subtitle { font-size: 10px; color: #6b7280; font-style: italic; margin-top: 2px; }
        .header-right { text-align: right; font-size: 10px; color: #475569; vertical-align: top; }
        .divider { height: 2px; background: #ef4444; margin: 6px 0 2px 0; }
        .divider-accent { height: 2px; background: #1d4ed8; margin: 2px 0 12px 0; }
        .title-block { text-align: center; margin: 14px 0 10px; }
        .title-block h1 { font-size: 15px; margin: 0; letter-spacing: 0.4px; }
        .title-block p { margin: 4px 0 0 0; font-size: 11px; color: #475569; font-style: italic; }
        .summary { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 12px; border-radius: 6px; font-size: 11px; line-height: 1.45; text-align: justify; }
        .section-title { font-size: 12px; font-weight: bold; margin: 16px 0 6px; color: #0f172a; text-transform: uppercase; letter-spacing: 0.6px; }
        .section-subtitle { font-size: 10px; color: #64748b; margin-bottom: 8px; }
        .stats-grid { margin-top: 10px; display: table; width: 100%; border-spacing: 6px; }
        .stat-card { display: table-cell; width: 33%; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; vertical-align: top; }
        .stat-label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.6px; }
        .stat-value { font-size: 13px; font-weight: bold; margin-top: 6px; color: #0f172a; }
        .chart-grid { display: table; width: 100%; border-spacing: 10px 0; margin-top: 6px; }
        .chart-col { display: table-cell; width: 50%; vertical-align: top; }
        .chart-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-bottom: 10px; }
        .chart-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; color: #0f172a; margin-bottom: 6px; }
        .line-chart { width: 100%; height: 160px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: auto; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 5px 6px; font-size: 9px; }
        .data-table th { background: #f8fafc; text-align: center; font-weight: bold; }
        .data-table td { vertical-align: top; }
        .data-table tbody tr:nth-child(even) td { background: #fcfdff; }
        .header-table td { border: none; padding: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #64748b; }
        .footer { position: fixed; bottom: -12px; left: 40px; right: 40px; font-size: 9px; color: #6b7280; z-index: 10; }
        .footer-line { border-top: 1px solid #e2e8f0; margin-bottom: 8px; }
        .footer-row { display: table; width: 100%; }
        .footer-cell { display: table-cell; vertical-align: middle; font-size: 9px; color: #64748b; }
        .footer-right { text-align: right; }
        .footer-item { display: inline-block; margin-left: 14px; }
        .col-no { width: 28px; }
        .col-desc { width: 220px; }
    </style>
</head>
<body>
    @php
        $fmtVal = function ($v) {
            return ((float) $v === 0.0) ? 'N/A' : number_format((float) $v, 0, ',', '.');
        };
        $fmtPct = function ($v, $base) {
            if ((float) $base === 0.0) return 'N/A';
            return number_format((float) $v, 2, ',', '.');
        };

        $multiOrigins = $multiMeta['origin_names'] ?? ($multiMeta['origins'] ?? null);
        $multiDests = $multiMeta['dest_names'] ?? ($multiMeta['dests'] ?? null);
        $originList = is_array($multiOrigins) ? implode(', ', array_values($multiOrigins)) : ($multiOrigins ?? '-');
        $destList = is_array($multiDests) ? implode(', ', array_values($multiDests)) : ($multiDests ?? 'Semua');
        $multiYearFrom = $multiMeta['year_from'] ?? null;
        $multiYearTo = $multiMeta['year_to'] ?? null;
    @endphp

    <div class="footer">
        <div class="footer-line"></div>
        <div class="footer-row">
            <div class="footer-cell">
                Remote demo environment | Jakarta, Indonesia
            </div>
            <div class="footer-cell footer-right">
                <span class="footer-item">portfolio@example.com</span>
                <span class="footer-item">portfolio-demo.example</span>
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
                    Sumber data: {{ $singleMeta['source_name'] ?? '-' }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>RINGKASAN PARIWISATA {{ $countryName }}</h1>
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">{{ $summaryNarrative }}</div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Wisatawan Masuk ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ !empty($showInboundSingle) ? $fmtVal($inNow) : 'N/A' }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Wisatawan Keluar ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ !empty($showOutboundSingle) ? $fmtVal($outNow) : 'N/A' }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ $fmtVal($totalNow) }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title">Tren Pariwisata</div>
    <div class="section-subtitle">
        Perkembangan wisatawan masuk dan keluar per tahun ({{ $unit }}).
    </div>
    <div class="chart-grid">
        @if (!empty($inboundChart) && !empty($showInboundSingle))
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Wisatawan Masuk</div>
                    <img class="line-chart" src="{{ $inboundChart }}" alt="Chart wisatawan masuk">
                </div>
            </div>
        @endif
        @if (!empty($outboundChart) && !empty($showOutboundSingle))
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Wisatawan Keluar</div>
                    <img class="line-chart" src="{{ $outboundChart }}" alt="Chart wisatawan keluar">
                </div>
            </div>
        @endif
    </div>
    @if (!empty($timeseries))
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">Tahun</th>
                    @if (!empty($showInboundSingle))
                        <th>Masuk ({{ $unit }})</th>
                    @endif
                    @if (!empty($showOutboundSingle))
                        <th>Keluar ({{ $unit }})</th>
                    @endif
                    <th>Total ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($timeseries as $row)
                    @php
                        $inValRaw = (int) ($row['inbound_count'] ?? 0);
                        $outValRaw = (int) ($row['outbound_count'] ?? 0);
                        $inVal = !empty($showInboundSingle) ? $inValRaw : 0;
                        $outVal = !empty($showOutboundSingle) ? $outValRaw : 0;
                        $totalVal = $inVal + $outVal;
                        $inDisplay = !empty($showInboundSingle) ? $fmtVal($inVal) : 'N/A';
                        $outDisplay = !empty($showOutboundSingle) ? $fmtVal($outVal) : 'N/A';
                        $bothZero = ($inVal === 0 && $outVal === 0);
                        $totalDisplay = $bothZero ? 'N/A' : $fmtVal($totalVal);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $row['year'] ?? '-' }}</td>
                        @if (!empty($showInboundSingle))
                            <td class="text-right">{{ $inDisplay }}</td>
                        @endif
                        @if (!empty($showOutboundSingle))
                            <td class="text-right">{{ $outDisplay }}</td>
                        @endif
                        <td class="text-right">{{ $totalDisplay }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($tableInbound) && !empty($showInboundSingle))
        <div class="section-title">Top Mitra Wisatawan Masuk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Jumlah ({{ $year ?? '-' }})</th>
                    <th>Jumlah ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tableInbound as $i => $row)
                    @php
                        $valNow = (int) ($row['value_now'] ?? 0);
                        $valPrev = (int) ($row['value_prev'] ?? 0);
                        $delta = $valNow - $valPrev;
                        $pct = $valPrev != 0 ? ($delta / $valPrev) * 100 : 0;
                        $deltaIsNA = ($valNow == 0 || $valPrev == 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row['label'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmtVal($valNow) }}</td>
                        <td class="text-right">{{ $fmtVal($valPrev) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtVal($delta) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtPct($pct, $valPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($tableOutbound) && !empty($showOutboundSingle))
        <div class="section-title">Top Mitra Wisatawan Keluar</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Jumlah ({{ $year ?? '-' }})</th>
                    <th>Jumlah ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tableOutbound as $i => $row)
                    @php
                        $valNow = (int) ($row['value_now'] ?? 0);
                        $valPrev = (int) ($row['value_prev'] ?? 0);
                        $delta = $valNow - $valPrev;
                        $pct = $valPrev != 0 ? ($delta / $valPrev) * 100 : 0;
                        $deltaIsNA = ($valNow == 0 || $valPrev == 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row['label'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmtVal($valNow) }}</td>
                        <td class="text-right">{{ $fmtVal($valPrev) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtVal($delta) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtPct($pct, $valPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($timeseries))
        <div class="section-title">Tren Multi Antar Negara</div>
        <div class="section-subtitle">
            Tren wisatawan masuk dan keluar untuk {{ $originList }} ke {{ $destList ?: 'Semua' }}
            pada rentang {{ $multiYearFrom ?? '-' }}{{ $multiYearTo ? ' - ' . $multiYearTo : '' }} ({{ $unit }}),
            sumber {{ $multiMeta['source_name'] ?? ($singleMeta['source_name'] ?? '-') }}.
        </div>
        <div class="chart-grid">
        @if (!empty($inboundChart) && !empty($showInboundSingle))
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Wisatawan Masuk</div>
                    <img class="line-chart" src="{{ $inboundChart }}" alt="Chart wisatawan masuk">
                </div>
            </div>
        @endif
        @if (!empty($outboundChart) && !empty($showOutboundSingle))
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Wisatawan Keluar</div>
                    <img class="line-chart" src="{{ $outboundChart }}" alt="Chart wisatawan keluar">
                </div>
            </div>
        @endif
    </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">Tahun</th>
                    @if (!empty($showInboundSingle))
                        <th>Masuk ({{ $unit }})</th>
                    @endif
                    @if (!empty($showOutboundSingle))
                        <th>Keluar ({{ $unit }})</th>
                    @endif
                    <th>Total ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($timeseries as $row)
                    @php
                        $inValRaw = (int) ($row['inbound_count'] ?? 0);
                        $outValRaw = (int) ($row['outbound_count'] ?? 0);
                        $inVal = !empty($showInboundSingle) ? $inValRaw : 0;
                        $outVal = !empty($showOutboundSingle) ? $outValRaw : 0;
                        $totalVal = $inVal + $outVal;
                        $inDisplay = !empty($showInboundSingle) ? $fmtVal($inVal) : 'N/A';
                        $outDisplay = !empty($showOutboundSingle) ? $fmtVal($outVal) : 'N/A';
                        $bothZero = ($inVal === 0 && $outVal === 0);
                        $totalDisplay = $bothZero ? 'N/A' : $fmtVal($totalVal);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $row['year'] ?? '-' }}</td>
                        @if (!empty($showInboundSingle))
                            <td class="text-right">{{ $inDisplay }}</td>
                        @endif
                        @if (!empty($showOutboundSingle))
                            <td class="text-right">{{ $outDisplay }}</td>
                        @endif
                        <td class="text-right">{{ $totalDisplay }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
