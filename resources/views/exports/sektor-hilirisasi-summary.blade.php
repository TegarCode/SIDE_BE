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
        .bar-chart { width: 100%; height: 170px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: auto; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 5px 6px; font-size: 9px; }
        .data-table th { background: #f8fafc; text-align: center; font-weight: bold; }
        .data-table td { vertical-align: top; }
        .data-table tbody tr:nth-child(even) td { background: #fcfdff; }
        .header-table td { border: none; padding: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
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
        $unit = $negaraMeta['unit'] ?? 'Ribu US$';
        $fmt = fn($v) => ((float)$v === 0.0) ? 'N/A' : number_format((float)$v, 0, ',', '.');
        $totalWorld = $negaraMeta['total_world'] ?? 0;
        $years = $negaraMeta['years'] ?? [];
        $produkYears = $produkMeta['years'] ?? ($produkMeta['tahun'] ?? []);
    @endphp
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
                    Sumber data: {{ $negaraMeta['sumber'] ?? '-' }}
                </td>
            </tr>
        </table>
        <div class="divider"></div>
        <div class="divider-accent"></div>
    </div>

    <div class="title-block">
        <h1>RINGKASAN PERDAGANGAN INTERNASIONAL SEKTOR HILIRISASI INDONESIA</h1>
        <p>
            Perdagangan hilirisasi global dan bilateral produk hilirisasi ({{ $produkMeta['reporter'][0]['nama'] ?? '-' }}
            ke {{ $produkMeta['partner'][0]['nama'] ?? '-' }})
        </p>
    </div>

    <div class="summary">
        Ringkasan ini menyajikan perdagangan internasional sektor hilirisasi berdasarkan daftar HS terpilih pada rentang tahun
        {{ $years ? min($years) : '-' }}-{{ $years ? max($years) : '-' }}. Bagian utama mencakup total dunia,
        tren ekspor-impor Indonesia ke dunia, serta pergerakan nilai perdagangan dan neraca pada setiap tahun.
        Selain itu, ringkasan memetakan 20 negara mitra terbesar pada tahun terbaru dan menampilkan struktur
        perdagangan hilirisasi berdasarkan sektor produk. Sub-bagian bilateral menekankan arah perdagangan dari
        asal ke tujuan yang dipilih, sementara visualisasi per sektor membantu melihat konsentrasi komoditas
        hilirisasi di setiap sektor.
    </div>

    <div class="section-title">Gambaran Umum</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Dunia ({{ $latestYear ?? '-' }})</div>
            <div class="stat-value">{{ $fmt($totalWorld) }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Ekspor Dunia</div>
            <div class="stat-value">{{ $fmt($exportByYear[$latestYear] ?? 0) }} {{ $unit }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Impor Dunia</div>
            <div class="stat-value">{{ $fmt($importByYear[$latestYear] ?? 0) }} {{ $unit }}</div>
        </div>
    </div>

    <div class="section-title">Tren Ekspor, Impor, Nilai, dan Neraca</div>
    <div class="section-subtitle">
        Tren berikut menunjukkan kinerja perdagangan hilirisasi Indonesia terhadap dunia pada periode
        {{ $years ? min($years) : '-' }}-{{ $years ? max($years) : '-' }} dengan satuan {{ $unit }}.
        Ekspor dan impor menggambarkan arus barang hilirisasi yang keluar dan masuk, sementara nilai perdagangan
        adalah total keduanya dan neraca menunjukkan posisi surplus/defisit pada setiap tahun.
    </div>
    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Ekspor</div>
                @if (!empty($chartExport))
                    <img class="bar-chart" src="{{ $chartExport }}" alt="Chart ekspor">
                @else
                    <div class="section-subtitle">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Impor</div>
                @if (!empty($chartImport))
                    <img class="bar-chart" src="{{ $chartImport }}" alt="Chart impor">
                @else
                    <div class="section-subtitle">Data tidak tersedia</div>
                @endif
            </div>
        </div>
    </div>
    <div class="chart-grid">
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Nilai Perdagangan</div>
                @if (!empty($chartTotal))
                    <img class="bar-chart" src="{{ $chartTotal }}" alt="Chart total">
                @else
                    <div class="section-subtitle">Data tidak tersedia</div>
                @endif
            </div>
        </div>
        <div class="chart-col">
            <div class="chart-box">
                <div class="chart-title">Neraca</div>
                @if (!empty($chartNeraca))
                    <img class="bar-chart" src="{{ $chartNeraca }}" alt="Chart neraca">
                @else
                    <div class="section-subtitle">Data tidak tersedia</div>
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
                <th>Nilai ({{ $unit }})</th>
                <th>Neraca ({{ $unit }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($years as $yr)
                @php
                    $exp = (float) ($exportByYear[$yr] ?? 0);
                    $imp = (float) ($importByYear[$yr] ?? 0);
                    $tot = (float) ($totalByYear[$yr] ?? 0);
                    $ner = (float) ($neracaByYear[$yr] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $yr }}</td>
                    <td class="text-right">{{ $fmt($exp) }}</td>
                    <td class="text-right">{{ $fmt($imp) }}</td>
                    <td class="text-right">{{ $fmt($tot) }}</td>
                    <td class="text-right">{{ $fmt($ner) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top 20 Negara</div>
    <div class="section-subtitle">
        Urutan mengikuti daftar hasil nilai perdagangan per negara dari sistem (tahun {{ $latestYear ?? '-' }}).
        Proporsi menunjukkan pangsa nilai perdagangan terhadap total dunia pada tahun yang sama.
        Seluruh angka dinyatakan dalam {{ $unit }}.
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Negara</th>
                <th>Ekspor {{ $latestYear ?? '-' }} ({{ $unit }})</th>
                <th>Impor {{ $latestYear ?? '-' }} ({{ $unit }})</th>
                <th>Nilai {{ $latestYear ?? '-' }} ({{ $unit }})</th>
                <th>Neraca {{ $latestYear ?? '-' }} ({{ $unit }})</th>
                <th>Proporsi {{ $latestYear ?? '-' }} (%)</th>
                <th>Ekspor {{ $prevYear ?? '-' }} ({{ $unit }})</th>
                <th>Impor {{ $prevYear ?? '-' }} ({{ $unit }})</th>
                <th>Nilai {{ $prevYear ?? '-' }} ({{ $unit }})</th>
                <th>Neraca {{ $prevYear ?? '-' }} ({{ $unit }})</th>
                <th>Proporsi {{ $prevYear ?? '-' }} (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach (array_slice($items, 0, 20) as $i => $row)
                @php
                    $total = (float) ($row['nilai_perdagangan'][$latestYear] ?? 0);
                    $neraca = (float) ($row['neraca'][$latestYear] ?? 0);
                    $export = ($total + $neraca) / 2;
                    $import = ($total - $neraca) / 2;
                    $prop = (float) ($row['proporsi'][$latestYear] ?? 0);
                    $totalPrev = (float) ($row['nilai_perdagangan'][$prevYear] ?? 0);
                    $neracaPrev = (float) ($row['neraca'][$prevYear] ?? 0);
                    $exportPrev = ($totalPrev + $neracaPrev) / 2;
                    $importPrev = ($totalPrev - $neracaPrev) / 2;
                    $propPrev = (float) ($row['proporsi'][$prevYear] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row['negara'] ?? '-' }}</td>
                    <td class="text-right">{{ $fmt($export) }}</td>
                    <td class="text-right">{{ $fmt($import) }}</td>
                    <td class="text-right">{{ $fmt($total) }}</td>
                    <td class="text-right">{{ $fmt($neraca) }}</td>
                    <td class="text-right">{{ number_format($prop, 2, ',', '.') }}</td>
                    <td class="text-right">{{ $fmt($exportPrev) }}</td>
                    <td class="text-right">{{ $fmt($importPrev) }}</td>
                    <td class="text-right">{{ $fmt($totalPrev) }}</td>
                    <td class="text-right">{{ $fmt($neracaPrev) }}</td>
                    <td class="text-right">{{ number_format($propPrev, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Tren Per Sektor Hilirisasi</div>
    <div class="section-subtitle">
        Visualisasi berikut menunjukkan tren nilai perdagangan per sektor hilirisasi berdasarkan daftar produk
        pada periode {{ $produkYears ? min($produkYears) : '-' }}-{{ $produkYears ? max($produkYears) : '-' }}.
        Grafik menampilkan total nilai perdagangan, sedangkan tabel di bawahnya merangkum ekspor, impor, nilai,
        dan neraca setiap tahun untuk sektor terkait. Semua nilai dalam {{ $unit }}.
    </div>
    @foreach ($sectorTrends as $sec)
        <div class="section-title">{{ $sec['sektor'] }}</div>
        <div class="chart-box">
            <div class="chart-title">Nilai Perdagangan {{ $sec['sektor'] }}</div>
            @if (!empty($sec['chart']))
                <img class="bar-chart" src="{{ $sec['chart'] }}" alt="Chart sektor {{ $sec['sektor'] }}">
            @else
                <div class="section-subtitle">Data tidak tersedia</div>
            @endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">Tahun</th>
                    <th>Ekspor ({{ $unit }})</th>
                    <th>Impor ({{ $unit }})</th>
                    <th>Nilai ({{ $unit }})</th>
                    <th>Neraca ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sec['years'] as $yr)
                    <tr>
                        <td class="text-center">{{ $yr }}</td>
                        <td class="text-right">{{ $fmt($sec['export'][$yr] ?? 0) }}</td>
                        <td class="text-right">{{ $fmt($sec['import'][$yr] ?? 0) }}</td>
                        <td class="text-right">{{ $fmt($sec['total'][$yr] ?? 0) }}</td>
                        <td class="text-right">{{ $fmt($sec['neraca'][$yr] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="section-title">Perdagangan Produk Hilirisasi Bilateral</div>
    <div class="section-subtitle">
        Bagian ini merangkum arus perdagangan produk hilirisasi dari {{ $produkMeta['reporter'][0]['nama'] ?? '-' }}
        ke {{ $produkMeta['partner'][0]['nama'] ?? '-' }} pada {{ $latestProdukYear ?? '-' }} dan membandingkannya
        dengan {{ $prevProdukYear ?? '-' }} sebagai tahun sebelumnya. Data dipecah per sektor untuk memudahkan
        identifikasi produk dominan dalam tiap sektor hilirisasi. Satuan yang digunakan adalah {{ $unit }}.
    </div>

    @foreach ($sectorProducts as $sec)
        <div class="section-title">Produk Hilirisasi - {{ $sec['sektor'] }}</div>

        <div class="section-title">Tabel Ekspor Produk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>HS</th>
                    <th class="col-desc">Deskripsi</th>
                    <th>Ekspor {{ $latestProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Ekspor {{ $prevProdukYear ?? '-' }} ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sec['produk'] as $i => $prod)
                    @php
                        $exp = (float) ($prod['ekspor'][$latestProdukYear] ?? 0);
                        $expPrev = (float) ($prod['ekspor'][$prevProdukYear] ?? 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                        <td>{{ $prod['namaHS'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmt($exp) }}</td>
                        <td class="text-right">{{ $fmt($expPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Tabel Impor Produk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>HS</th>
                    <th class="col-desc">Deskripsi</th>
                    <th>Impor {{ $latestProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Impor {{ $prevProdukYear ?? '-' }} ({{ $unit }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sec['produk'] as $i => $prod)
                    @php
                        $imp = (float) ($prod['impor'][$latestProdukYear] ?? 0);
                        $impPrev = (float) ($prod['impor'][$prevProdukYear] ?? 0);
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                        <td>{{ $prod['namaHS'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmt($imp) }}</td>
                        <td class="text-right">{{ $fmt($impPrev) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Tabel Nilai & Neraca Produk</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>HS</th>
                    <th class="col-desc">Deskripsi</th>
                    <th>Nilai {{ $latestProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Neraca {{ $latestProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Pangsa {{ $latestProdukYear ?? '-' }} (%)</th>
                    <th>Nilai {{ $prevProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Neraca {{ $prevProdukYear ?? '-' }} ({{ $unit }})</th>
                    <th>Pangsa {{ $prevProdukYear ?? '-' }} (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sec['produk'] as $i => $prod)
                    @php
                        $total = (float) ($prod['total'][$latestProdukYear] ?? 0);
                        $share = (float) ($prod['share'][$latestProdukYear] ?? 0);
                        $exp = (float) ($prod['ekspor'][$latestProdukYear] ?? 0);
                        $imp = (float) ($prod['impor'][$latestProdukYear] ?? 0);
                        $neraca = $exp - $imp;
                        $totalPrev = (float) ($prod['total'][$prevProdukYear] ?? 0);
                        $sharePrev = (float) ($prod['share'][$prevProdukYear] ?? 0);
                        $expPrev = (float) ($prod['ekspor'][$prevProdukYear] ?? 0);
                        $impPrev = (float) ($prod['impor'][$prevProdukYear] ?? 0);
                        $neracaPrev = $expPrev - $impPrev;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center">{{ $prod['kodeHS'] ?? '-' }}</td>
                        <td>{{ $prod['namaHS'] ?? '-' }}</td>
                        <td class="text-right">{{ $fmt($total) }}</td>
                        <td class="text-right">{{ $fmt($neraca) }}</td>
                        <td class="text-right">{{ number_format($share, 2, ',', '.') }}</td>
                        <td class="text-right">{{ $fmt($totalPrev) }}</td>
                        <td class="text-right">{{ $fmt($neracaPrev) }}</td>
                        <td class="text-right">{{ number_format($sharePrev, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
