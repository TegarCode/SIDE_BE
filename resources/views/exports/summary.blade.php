<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 80px 40px 60px 40px;
            /* top right bottom left */
            footer: page-footer;
        }

        body {
            font-family: sans-serif;
            font-size: 11px;
            margin: 30px;
        }

        h2,
        h3,
        h4,
        p {
            margin: 4px 0;
            text-align: center;
        }

        .header-bar {
            background-color: #bf8f00;
            color: white;
            text-align: center;
            padding: 14px;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .header-key {
            background-color: #ffc000;
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 20px;
        }

        .info-line {
            text-align: left;
            font-weight: bold;
            margin-top: 10px;
        }

        .justify {
            text-align: justify;
        }

        .summary-section {
            margin-top: 15px;
        }

        .summary-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .summary-item-text {
            text-align: justify;
            flex: 1;
        }

        .strategy-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin: 15px 0 5px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: auto;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 9px;
            text-align: center;
            word-wrap: break-word;
        }

        th {
            background-color: #f0f0f0;
        }

        .footer {
            position: fixed;
            bottom: 15px;
            width: 100%;
            text-align: center;
            font-size: 9px;
            line-height: 1.2;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>

    <div class="header-bar">DATA SUMMARY</div>

    <h3 class="info-line">Portfolio Demo: {{ $tanggalCetak }}</h3>

    <h2>ANALISIS INTELIJEN PASAR :</h2>
    <h2>PEMETAAN DAN IDENTIFIKASI PRODUK POTENSIAL</h2>
    <h2>{{ $originName }} ke {{ $destinationName }} ({{ $tanggalCetak }})</h2>

    <div class="header-key mb-4">KEY INSIGHT</div>

    <p class="justify">
        Data Summary analisis intelijen pasar dalam pemetaan dan identifikasi produk potensial
        {{ $originName }} - {{ $destinationName }} menggunakan perangkat analisis RCA (Revealed Comparative Advantage)
        dan CMSA (Constant Market Share Analysis) Product Classification Model yang dirumuskan oleh Kiki Verico (2020).
        Hasil analisis intelijen pasar menyimpulkan rekomendasi kebijakan sebagai berikut:
    </p>

    <div class="summary-section">
        @foreach ($summaryList as $i => $item)
            <div class="summary-item">
                <div class="summary-item-text">{{ $i + 1 }}. {{ $item['judul'] }} {{ $item['produk'] }}.</div>
            </div>
        @endforeach
    </div>
    <div style="page-break-before: always;"></div>

    @foreach ($tablesData as $index => $table)
        @if (!$loop->first)
            <div style="page-break-before: always;"></div>
        @endif
        <p class="strategy-title">HASIL RCA-CMSA PRODUK POTENSIAL {{ $originName }} - {{ $destinationName }}</p>
        <p class="strategy-title">Rekomendasi Strategi: {{ strtoupper($table['strategy']) }}</p>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>HS Code</th>
                    <th>Nama Produk</th>
                    <th>RCA {{ $originName }}</th>
                    <th>CMSA {{ $originName }}</th>
                    <th>Class {{ $originName }}</th>
                    <th>RCA {{ $destinationName }}</th>
                    <th>CMSA {{ $destinationName }}</th>
                    <th>Class {{ $destinationName }}</th>
                    <th>Strategi</th>
                    <th>{{ $originName }} ke Dunia</th>
                    <th>{{ $originName }} ke {{ $destinationName }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($table['data'] as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->HsCode }}</td>
                        <td style="text-align: left">{{ $item->NamaProduk }}</td>
                        <td>{{ number_format($item->RCA_Asal, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->CMSA_Asal, 2, ',', '.') }}</td>
                        <td>{{ $item->Class_Asal }}</td>
                        <td>{{ number_format($item->RCA_Tujuan, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->CMSA_Tujuan, 2, ',', '.') }}</td>
                        <td>{{ $item->Class_Tujuan }}</td>
                        <td>{{ $item->Strategy }}</td>
                        <td>{{ number_format($item->Asal_World, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->Ekspor_RI_To_Partner, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px; font-size: 9px; text-align: center; line-height: 1.2; color: #555;">
            Portfolio Demo Team<br>
            Strategy & Analytics Showcase<br>
            Sample Repository
        </div>
    @endforeach

</body>

</html>
