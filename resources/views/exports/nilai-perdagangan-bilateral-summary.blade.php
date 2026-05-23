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
            width: 25%;
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

        .col-hs {
            width: 60px;
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
        <h1>EVALUASI KINERJA NILAI PERDAGANGAN BILATERAL {{ $latestYear }}</h1>
        @if (!empty($headlineDesc))
            <p class="muted" style="margin-top: 4px;">{{ $headlineDesc }}</p>
        @endif
        <p>Ringkasan Eksekutif</p>
    </div>

    <div class="summary">
        {{ $summaryNarrative }}
    </div>

    <div class="section-title">I. KINERJA PERDAGANGAN BILATERAL</div>
    <div class="section-subtitle">
        Bagian ini menyajikan ringkasan tren nilai perdagangan bilateral dan peringkat mitra dagang utama pada tahun {{ $latestYear }}.
    </div>

    <div class="section-title" style="margin-top: 6px;">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Perdagangan ({{ $latestYear }})</div>
            <div class="stat-value">{{ number_format($totalTradeLatest, 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ekspor ({{ $latestYear }})</div>
            <div class="stat-value">{{ number_format($totalExportLatest, 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Impor ({{ $latestYear }})</div>
            <div class="stat-value">{{ number_format($totalImportLatest, 0, ',', '.') }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Neraca ({{ $latestYear }})</div>
            <div class="stat-value">{{ number_format($totalBalanceLatest, 0, ',', '.') }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title" style="margin-top: 10px;">Ringkasan Nilai</div>
    <div class="muted">
        @if ($prevYear)
            Perbandingan nilai {{ $latestYear }} dengan {{ $prevYear }}.
        @else
            Ringkasan nilai pada tahun {{ $latestYear }}.
        @endif
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Komponen</th>
                <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                @if ($prevYear)
                    <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($totalSummaryRows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right">{{ number_format($row['latest'], 0, ',', '.') }}</td>
                    @if ($prevYear)
                        <td class="text-right">{{ $row['prev'] === null ? '-' : number_format($row['prev'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $row['delta'] === null ? '-' : number_format($row['delta'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $row['pct'] === null ? '-' : number_format($row['pct'], 2, ',', '.') . '%' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Tren Total Nilai Perdagangan</div>
                @if (!empty($trendLineChart))
                    <img class="line-chart" src="{{ $trendLineChart }}" alt="Line chart tren perdagangan">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">
                    {{ ($topPartnerCount ?? 0) >= 5 ? 'Top 5 Mitra Dagang' : 'Mitra Dagang Utama' }} ({{ $latestYear }})
                </div>
                @if (!empty($top5BarChart))
                    <img class="line-chart" src="{{ $top5BarChart }}" alt="Top mitra dagang">
                @else
                    <div class="muted" style="font-size: 10px;">Data tidak tersedia</div>
                @endif
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    <div class="section-title">II. TABEL RINGKASAN</div>
    <div class="section-title" style="margin-top: 8px;">
        {{ count($partnerRows) >= 10 ? 'Tabel Top 10 Mitra Dagang' : 'Tabel Mitra Dagang' }}
    </div>
    <div class="muted">
        @if ($prevYear)
            Perbandingan nilai perdagangan tahun {{ $latestYear }} dengan tahun {{ $prevYear }} beserta perubahan absolut dan persentase.
        @else
            Ringkasan nilai perdagangan pada tahun {{ $latestYear }}.
        @endif
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Mitra Dagang</th>
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
                    <td class="text-right">{{ number_format($row['latest'], 0, ',', '.') }}</td>
                    @if ($prevYear)
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

    <div class="section-title" style="margin-top: 14px;">Tabel Top 10 Komoditas</div>
    <div class="muted">
        @if ($prevYear)
            Top komoditas berdasarkan nilai perdagangan total (ekspor + impor) pada tahun {{ $latestYear }} dibandingkan {{ $prevYear }}.
        @else
            Top komoditas berdasarkan nilai perdagangan total (ekspor + impor) pada tahun {{ $latestYear }}.
        @endif
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-hs">HS</th>
                <th>Komoditas</th>
                <th>Nilai {{ $latestYear }} ({{ $unit }})</th>
                @if ($prevYear)
                    <th>Nilai {{ $prevYear }} ({{ $unit }})</th>
                    <th>Perubahan</th>
                    <th>Perubahan (%)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($produkRows as $i => $row)
                <tr>
                    <td class="text-center col-no">{{ $i + 1 }}</td>
                    <td class="text-center col-hs">{{ $row['kode'] }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td class="text-right">{{ number_format($row['latest'], 0, ',', '.') }}</td>
                    @if ($prevYear)
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

    @if (count($tujuanEksporRows))
        <div class="section-title" style="margin-top: 14px;">Negara Tujuan Ekspor</div>
        <div class="muted">Daftar negara tujuan ekspor untuk 10 komoditas utama pada tahun {{ $latestYear }}.</div>
        @if (!empty($tujuanEksporDesc))
            <div class="muted">Tujuan utama: {{ $tujuanEksporDesc }}.</div>
        @endif
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-hs">HS</th>
                    <th>Komoditas</th>
                    <th>Tujuan Ekspor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tujuanEksporRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td class="text-center col-hs">{{ $row['kode'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['tujuan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($kompetitorEksporRows))
        <div class="section-title" style="margin-top: 14px;">Kompetitor Top Tujuan Ekspor</div>
        <div class="muted">Kompetitor utama pada tujuan ekspor utama dan posisi Indonesia.</div>
        @if (!empty($kompetitorEksporDesc))
            <div class="muted">Tujuan negara: {{ $kompetitorEksporDesc }}.</div>
        @endif
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-hs">HS</th>
                    <th>Komoditas</th>
                    <th>Tujuan Ekspor Utama</th>
                    <th>Negara Kompetitor</th>
                    <th>Rank Indonesia</th>
                    <th>Nilai Kompetitor ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kompetitorEksporRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td class="text-center col-hs">{{ $row['kode'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['tujuan'] }}</td>
                        <td>{{ $row['negara'] }}</td>
                        <td class="text-center">{{ $row['rank'] }}</td>
                        <td class="text-right">{{ $row['nilai'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($tujuanImporRows))
        <div class="section-title" style="margin-top: 14px;">Negara Asal Impor</div>
        <div class="muted">Daftar negara asal impor untuk 10 komoditas utama pada tahun {{ $latestYear }}.</div>
        @if (!empty($tujuanImporDesc))
            <div class="muted">Asal utama: {{ $tujuanImporDesc }}.</div>
        @endif
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-hs">HS</th>
                    <th>Komoditas</th>
                    <th>Asal Impor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tujuanImporRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td class="text-center col-hs">{{ $row['kode'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['tujuan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($kompetitorImporRows))
        <div class="section-title" style="margin-top: 14px;">Kompetitor Top Asal Impor</div>
        <div class="muted">Kompetitor utama pada asal impor utama dan posisi Indonesia.</div>
        @if (!empty($kompetitorImporDesc))
            <div class="muted">Tujuan negara: {{ $kompetitorImporDesc }}.</div>
        @endif
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-hs">HS</th>
                    <th>Komoditas</th>
                    <th>Asal Impor Utama</th>
                    <th>Negara Kompetitor</th>
                    <th>Rank Indonesia</th>
                    <th>Nilai Kompetitor ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kompetitorImporRows as $i => $row)
                    <tr>
                        <td class="text-center col-no">{{ $i + 1 }}</td>
                        <td class="text-center col-hs">{{ $row['kode'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['tujuan'] }}</td>
                        <td>{{ $row['negara'] }}</td>
                        <td class="text-center">{{ $row['rank'] }}</td>
                        <td class="text-right">{{ $row['nilai'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
