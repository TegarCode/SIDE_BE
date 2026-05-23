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
        <h1>TOP PARIWISATA MITRA {{ $meta['tujuan'] ?? '-' }}</h1>
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">{{ $summaryNarrative }}</div>

    @if (!empty($inboundRows))
        <div class="section-title">Pariwisata Masuk</div>
        <div class="section-subtitle">
            Tahun {{ $meta['latest_year'] ?? '-' }} dibanding {{ $meta['prev_year'] ?? '-' }} (Orang)
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Nilai {{ $meta['prev_year'] ?? '-' }}</th>
                    <th>Nilai {{ $meta['latest_year'] ?? '-' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inboundRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td>{{ $row['country'] ?? '-' }}</td>
                        <td class="text-right">{{ number_format((int) ($row['value'.$meta['prev_year']] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((int) ($row['value'.$meta['latest_year']] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($outboundRows))
        <div class="section-title">Pariwisata Keluar</div>
        <div class="section-subtitle">
            Tahun {{ $meta['latest_year'] ?? '-' }} dibanding {{ $meta['prev_year'] ?? '-' }} (Orang)
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Nilai {{ $meta['prev_year'] ?? '-' }}</th>
                    <th>Nilai {{ $meta['latest_year'] ?? '-' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($outboundRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td>{{ $row['country'] ?? '-' }}</td>
                        <td class="text-right">{{ number_format((int) ($row['value'.$meta['prev_year']] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((int) ($row['value'.$meta['latest_year']] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
