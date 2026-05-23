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
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; background: #eff6ff; color: #1d4ed8; font-size: 9px; }
        .block { margin-top: 10px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .col-desc { width: 220px; }
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
                    Sumber data: {{ $meta['sumber'] ?? '-' }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>TOP PERDAGANGAN MITRA {{ $meta['asal'] ?? '-' }} {{ $latestYear ?? '-' }}</h1>
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">{{ $summaryNarrative }}</div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Perdagangan ({{ $latestYear ?? '-' }})</div>
            <div class="stat-value">{{ number_format((int) ($meta['total_world'] ?? 0), 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ekspor ({{ $latestYear ?? '-' }})</div>
            <div class="stat-value">{{ number_format((int) ($meta['total_export_y2'] ?? 0), 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Impor ({{ $latestYear ?? '-' }})</div>
            <div class="stat-value">{{ number_format((int) ($meta['total_import_y2'] ?? 0), 0, ',', '.') }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title">Top Mitra Perdagangan</div>
    <div class="section-subtitle">
        Perbandingan nilai perdagangan tahun {{ $latestYear }} dan {{ $prevYear }}.
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Negara</th>
                <th>Ekspor {{ $latestYear }} ({{ $unit }})</th>
                <th>Ekspor {{ $prevYear }} ({{ $unit }})</th>
                <th>Impor {{ $latestYear }} ({{ $unit }})</th>
                <th>Impor {{ $prevYear }} ({{ $unit }})</th>
                <th>Total {{ $latestYear }} ({{ $unit }})</th>
                <th>Total {{ $prevYear }} ({{ $unit }})</th>
                <th>Proporsi dari Total {{ $latestYear }} (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($partnerRows as $i => $row)
                @php
                    $expLatest = (int) ($row['ekspor_latest_year'] ?? 0);
                    $expPrev = (int) ($row['ekspor_prev_year'] ?? 0);
                    $impLatest = (int) ($row['impor_latest_year'] ?? 0);
                    $impPrev = (int) ($row['impor_prev_year'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $row['rank'] ?? ($i + 1) }}</td>
                    <td>{{ $row['negara'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($expLatest, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($expPrev, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($impLatest, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($impPrev, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['total_latest_year'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['total_prev_year'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($row['proporsi_y2'] ?? 0), 2, ',', '.') }}</td>
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
                <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                <th>Perubahan ({{ $unit }})</th>
                <th>Pangsa pasar (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topProdukEkspor as $i => $prod)
                @php
                    $prevVal = (int) ($prod['nilai_prev_year'] ?? 0);
                    $latestVal = (int) ($prod['nilai_latest_year'] ?? 0);
                    $deltaVal = $latestVal - $prevVal;
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                    <td>{{ $prod['namaHS'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($prevVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($latestVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ ($deltaVal >= 0 ? '+' : '') . number_format($deltaVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($prod['share'] ?? 0), 2, ',', '.') }}</td>
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
                <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                <th>Perubahan ({{ $unit }})</th>
                <th>Pangsa pasar (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topProdukImpor as $i => $prod)
                @php
                    $prevVal = (int) ($prod['nilai_prev_year'] ?? 0);
                    $latestVal = (int) ($prod['nilai_latest_year'] ?? 0);
                    $deltaVal = $latestVal - $prevVal;
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                    <td>{{ $prod['namaHS'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format($prevVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($latestVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ ($deltaVal >= 0 ? '+' : '') . number_format($deltaVal, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($prod['share'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Kompetitor Ekspor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>HS</th>
                <th class="col-desc">Deskripsi</th>
                <th>Kompetitor Global</th>
                <th>Kompetitor ASEAN</th>
                <th>Rank Indonesia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topProdukEkspor as $i => $prod)
                @php
                    $globalText = $prod['kompetitor_global_display'] ?? '-';
                    $aseanText = $prod['kompetitor_asean_display'] ?? '-';
                    $rankIdn = $prod['rank_indonesia'] ?? '-';
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                    <td>{{ $prod['namaHS'] ?? '-' }}</td>
                    <td>{{ $globalText }}</td>
                    <td>{{ $aseanText }}</td>
                    <td class="text-center">{{ $rankIdn }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Kompetitor Impor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>HS</th>
                <th class="col-desc">Deskripsi</th>
                <th>Kompetitor Global</th>
                <th>Kompetitor ASEAN</th>
                <th>Rank Indonesia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($topProdukImpor as $i => $prod)
                @php
                    $globalText = $prod['kompetitor_global_display'] ?? '-';
                    $aseanText = $prod['kompetitor_asean_display'] ?? '-';
                    $rankIdn = $prod['rank_indonesia'] ?? '-';
                @endphp
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                    <td>{{ $prod['namaHS'] ?? '-' }}</td>
                    <td>{{ $globalText }}</td>
                    <td>{{ $aseanText }}</td>
                    <td class="text-center">{{ $rankIdn }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
