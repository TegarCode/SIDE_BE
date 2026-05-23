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
        .col-desc { width: 240px; }
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
                <span class="footer-item">data1.pskikad@kemlu.go.id</span>
                <span class="footer-item">side.kemlu.go.id</span>
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
                    Sumber data: {{ $meta['source_name'] ?? '-' }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>RINGKASAN PERDAGANGAN {{ $originName }} - {{ $destName }}</h1>
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">{{ $summaryNarrative }}</div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Ekspor ({{ $meta['year'] ?? '-' }})</div>
            <div class="stat-value">{{ number_format($exportNow, 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Impor ({{ $meta['year'] ?? '-' }})</div>
            <div class="stat-value">{{ number_format($importNow, 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total ({{ $meta['year'] ?? '-' }})</div>
            <div class="stat-value">{{ number_format($totalNow, 0, ',', '.') }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title">Tren Perdagangan</div>
    <div class="section-subtitle">Ekspor, impor, dan total per tahun ({{ $unit }}).</div>
    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Ekspor</div>
                @if (!empty($exportChart))
                    <img class="line-chart" src="{{ $exportChart }}" alt="Chart ekspor">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Impor</div>
                @if (!empty($importChart))
                    <img class="line-chart" src="{{ $importChart }}" alt="Chart impor">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
    </div>
    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Total Perdagangan</div>
                @if (!empty($totalChart))
                    <img class="line-chart" src="{{ $totalChart }}" alt="Chart total perdagangan">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Neraca Perdagangan</div>
                @if (!empty($balanceChart))
                    <img class="line-chart" src="{{ $balanceChart }}" alt="Chart neraca perdagangan">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">Tahun</th>
                <th>Ekspor ({{ $unit }})</th>
                <th>Impor ({{ $unit }})</th>
                <th>Total ({{ $unit }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timeseries as $row)
                @php
                    $exp = (int) ($row['export'] ?? 0);
                    $imp = (int) ($row['import'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $row['year'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($exp, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($imp, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($exp + $imp, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top Produk Ekspor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>HS</th>
                <th class="col-desc">Deskripsi</th>
                <th>Nilai ({{ $unit }})</th>
                <th>Pangsa pasar (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topExport as $i => $prod)
                @php
                    $val = (int) ($prod['value_od'] ?? 0);
                    $share = $exportNow > 0 ? ($val / $exportNow) * 100 : 0;
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['code'] ?? '-' }}</td>
                    <td>{{ $prod['label'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($val, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($share, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top Produk Impor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>HS</th>
                <th class="col-desc">Deskripsi</th>
                <th>Nilai ({{ $unit }})</th>
                <th>Pangsa pasar (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topImport as $i => $prod)
                @php
                    $val = (int) ($prod['value_od'] ?? 0);
                    $share = $importNow > 0 ? ($val / $importNow) * 100 : 0;
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['code'] ?? '-' }}</td>
                    <td>{{ $prod['label'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($val, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($share, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
