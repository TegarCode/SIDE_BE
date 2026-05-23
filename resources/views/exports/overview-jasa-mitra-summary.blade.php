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
    @php
        $fmtVal = function ($v) {
            return ((float) $v === 0.0) ? 'N/A' : number_format((float) $v, 0, ',', '.');
        };
        $fmtPct = function ($v, $base) {
            if ((float) $base === 0.0) return 'N/A';
            return number_format((float) $v, 2, ',', '.');
        };
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
                    Sumber data: {{ $overviewMeta['source_name'] ?? ($countryMeta['source_name'] ?? '-') }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>RINGKASAN JASA {{ $originName }}</h1>
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">
        Ringkasan ini menyajikan gambaran jasa untuk {{ $originName }} berdasarkan sumber {{ $overviewMeta['source_name'] ?? '-' }}
        pada tahun terbaru {{ $year ?? '-' }}. Fokus ringkasan mencakup nilai jasa masuk dan keluar, perubahan dibanding
        tahun sebelumnya ({{ $prevYear ?? '-' }}), serta pola pergerakan dari data tren tahunan.
        Pada bagian mitra, ringkasan menyoroti negara tujuan utama pada jasa keluar dan negara asal pada jasa masuk,
        sehingga memberikan konteks konsentrasi pasar dan arah hubungan jasa.
        Bagian bilateral merinci alur jasa dari {{ $originName }} ke {{ $destName }} beserta komposisi jasa dominan,
        yang dapat dipakai untuk membaca sektor unggulan, potensi penguatan kerja sama, serta peluang diversifikasi layanan.
    </div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Jasa Masuk ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ $showInboundSingle ? $fmtVal($countryInNow) : 'N/A' }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Jasa Keluar ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ $showOutboundSingle ? $fmtVal($countryOutNow) : 'N/A' }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total ({{ $year ?? '-' }})</div>
            <div class="stat-value">{{ $fmtVal(($countryInNow ?? 0) + ($countryOutNow ?? 0)) }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title">Tren Jasa</div>
    <div class="section-subtitle">
        Tren jasa masuk dan keluar pada {{ $originName }} ke Dunia ({{ $unit }}).
    </div>
    <div class="chart-grid">
        @if (!empty($inboundChart) && $showInboundSingle)
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Jasa Masuk</div>
                    <img class="line-chart" src="{{ $inboundChart }}" alt="Chart jasa masuk">
                </div>
            </div>
        @endif
        @if (!empty($outboundChart) && $showOutboundSingle)
            <div class="chart-col">
                <div class="chart-box">
                    <div class="chart-title">Tren Jasa Keluar</div>
                    <img class="line-chart" src="{{ $outboundChart }}" alt="Chart jasa keluar">
                </div>
            </div>
        @endif
    </div>

    @if (!empty($timeseries))
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">Tahun</th>
                    @if ($showInboundSingle)
                        <th>Masuk ({{ $unit }})</th>
                    @endif
                    @if ($showOutboundSingle)
                        <th>Keluar ({{ $unit }})</th>
                    @endif
                    <th>Total ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($timeseries as $row)
                    @php
                        $inVal = (float) ($row['inbound_value'] ?? 0);
                        $outVal = (float) ($row['outbound_value'] ?? 0);
                        $totalVal = ($showInboundSingle ? $inVal : 0) + ($showOutboundSingle ? $outVal : 0);
                        $totalDisplay = $totalVal == 0 ? 'N/A' : $fmtVal($totalVal);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $row['year'] ?? '-' }}</td>
                        @if ($showInboundSingle)
                            <td class="text-right">{{ $fmtVal($inVal) }}</td>
                        @endif
                        @if ($showOutboundSingle)
                            <td class="text-right">{{ $fmtVal($outVal) }}</td>
                        @endif
                        <td class="text-right">{{ $totalDisplay }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($topCountriesInbound) && $showInboundSingle)
        <div class="section-title">Top Mitra Jasa Masuk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Nilai ({{ $year ?? '-' }})</th>
                    <th>Nilai ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topCountriesInbound as $i => $row)
                    @php
                        $valNow = (float) ($row['value_now'] ?? 0);
                        $valPrev = (float) ($row['value_prev'] ?? 0);
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

    @if (!empty($topCountriesOutbound) && $showOutboundSingle)
        <div class="section-title">Top Mitra Jasa Keluar</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Negara</th>
                    <th>Nilai ({{ $year ?? '-' }})</th>
                    <th>Nilai ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topCountriesOutbound as $i => $row)
                    @php
                        $valNow = (float) ($row['value_now'] ?? 0);
                        $valPrev = (float) ($row['value_prev'] ?? 0);
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

    @if (!empty($topServicesInbound) || !empty($topServicesOutbound))
        <div class="section-title">Bilateral Jasa</div>
        <div class="section-subtitle">
            Alur jasa tahun {{ $year ?? '-' }} dari {{ $originName }} ke {{ $destName }}.
        </div>
        <div class="chart-grid">
            @if (!empty($inboundChart) && $showInboundBilateral)
                <div class="chart-col">
                    <div class="chart-box">
                        <div class="chart-title">Tren Jasa Masuk</div>
                        <img class="line-chart" src="{{ $inboundChart }}" alt="Chart jasa masuk">
                    </div>
                </div>
            @endif
            @if (!empty($outboundChart) && $showOutboundBilateral)
                <div class="chart-col">
                    <div class="chart-box">
                        <div class="chart-title">Tren Jasa Keluar</div>
                        <img class="line-chart" src="{{ $outboundChart }}" alt="Chart jasa keluar">
                    </div>
                </div>
            @endif
        </div>
        @if (!empty($timeseries))
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-no">Tahun</th>
                        @if ($showInboundBilateral)
                            <th>Masuk ({{ $unit }})</th>
                        @endif
                        @if ($showOutboundBilateral)
                            <th>Keluar ({{ $unit }})</th>
                        @endif
                        <th>Total ({{ $unit }})</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timeseries as $row)
                        @php
                            $inVal = (float) ($row['inbound_value'] ?? 0);
                            $outVal = (float) ($row['outbound_value'] ?? 0);
                            $totalVal = ($showInboundBilateral ? $inVal : 0) + ($showOutboundBilateral ? $outVal : 0);
                            $totalDisplay = $totalVal == 0 ? 'N/A' : $fmtVal($totalVal);
                        @endphp
                        <tr>
                            <td class="text-center">{{ $row['year'] ?? '-' }}</td>
                            @if ($showInboundBilateral)
                                <td class="text-right">{{ $fmtVal($inVal) }}</td>
                            @endif
                            @if ($showOutboundBilateral)
                                <td class="text-right">{{ $fmtVal($outVal) }}</td>
                            @endif
                            <td class="text-right">{{ $totalDisplay }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if (!empty($topServicesInbound))
        <div class="section-title">Top Jasa Masuk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-desc">Jasa</th>
                    <th>Nilai ({{ $year ?? '-' }})</th>
                    <th>Nilai ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topServicesInbound as $i => $row)
                    @php
                        $valNow = (float) ($row['value_now'] ?? ($row['value'] ?? 0));
                        $valPrev = (float) ($row['value_prev'] ?? 0);
                        $delta = $valNow - $valPrev;
                        $pct = $valPrev != 0 ? ($delta / $valPrev) * 100 : 0;
                        $deltaIsNA = ($valNow == 0 || $valPrev == 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row['label'] ?? $row['code'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmtVal($valNow) }}</td>
                        <td class="text-right">{{ $fmtVal($valPrev) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtVal($delta) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtPct($pct, $valPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($topServicesOutbound))
        <div class="section-title">Top Jasa Keluar</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-desc">Jasa</th>
                    <th>Nilai ({{ $year ?? '-' }})</th>
                    <th>Nilai ({{ $prevYear ?? '-' }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($topServicesOutbound as $i => $row)
                    @php
                        $valNow = (float) ($row['value_now'] ?? ($row['value'] ?? 0));
                        $valPrev = (float) ($row['value_prev'] ?? 0);
                        $delta = $valNow - $valPrev;
                        $pct = $valPrev != 0 ? ($delta / $valPrev) * 100 : 0;
                        $deltaIsNA = ($valNow == 0 || $valPrev == 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row['label'] ?? $row['code'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmtVal($valNow) }}</td>
                        <td class="text-right">{{ $fmtVal($valPrev) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtVal($delta) }}</td>
                        <td class="text-right">{{ $deltaIsNA ? 'N/A' : $fmtPct($pct, $valPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
