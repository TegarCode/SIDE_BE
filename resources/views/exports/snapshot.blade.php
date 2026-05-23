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
        }

        .header-bar {
            background-color: #008080;
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        h3,
        p {
            margin: 4px 0;
            text-align: center;
        }

        .info-line {
            text-align: left;
            font-weight: bold;
            margin-top: 10px;
        }

        .strategy-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            text-align: left;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: auto;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 10px;
        }

        th {
            background-color: #f0f0f0;
        }

        .text-left {
            text-align: left;
        }

        .page-break {
            page-break-before: always;
        }

        .footer-text {
            text-align: center;
            font-size: 9px;
            line-height: 1.2;
            color: #555;
        }
    </style>
</head>

<body>

    @foreach ($dataPerStrategy as $section)
        @if (!$loop->first)
            <div style="page-break-before: always;"></div>
        @endif

        <div class="header-bar">STAT- SNAPSHOTS</div>

        <h3 class="info-line">BSKLN: {{ $tanggalCetak }}</h3>
        <h3>STAT-SNAPSHOTS ANALISIS INTELIGEN PASAR :</h3>
        <h3>HASIL RCA-CMSA PEMETAAN DAN IDENTIFIKASI PRODUK POTENSIAL</h3>
        <h3>{{ strtoupper($originName) }} - {{ strtoupper($destinationName) }}</h3>
        <p class="strategy-title">STRATEGY : {{ strtoupper($section['strategy']) }}</p>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode Produk</th>
                    <th>Deskripsi Produk</th>
                    <th>RCA {{ $originName }}</th>
                    <th>CMSA {{ $originName }}</th>
                    <th>Class {{ $originName }}</th>
                    <th>RCA {{ $destinationName }}</th>
                    <th>CMSA {{ $destinationName }}</th>
                    <th>Kelas {{ $destinationName }}</th>
                    <th>Strategi</th>
                    <th>{{ $originName }} ke Dunia</th>
                    <th>{{ $originName }} ke {{ $destinationName }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($section['data'] as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->HsCode }}</td>
                        <td class="text-left">{{ $row->NamaProduk }}</td>
                        <td>{{ number_format($row->RCA_Asal, 2, ',', '.') }}</td>
                        <td>{{ number_format($row->CMSA_Asal, 2, ',', '.') }}</td>
                        <td>{{ $row->Class_Asal }}</td>
                        <td>{{ number_format($row->RCA_Tujuan, 2, ',', '.') }}</td>
                        <td>{{ number_format($row->CMSA_Tujuan, 2, ',', '.') }}</td>
                        <td>{{ $row->Class_Tujuan }}</td>
                        <td>{{ $row->Strategy }}</td>
                        <td>{{ number_format($row->Asal_World, 2, ',', '.') }}</td>
                        <td>{{ number_format($row->Ekspor_RI_To_Partner, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Footer manual di bawah setiap halaman --}}
        <div style="margin-top: 20px; font-size: 9px; text-align: center; line-height: 1.2; color: #555;">
            Badan Strategi Kebijakan Luar Negeri (BSKLN)<br>
            Kementerian Luar Negeri<br>
            Republik Indonesia
        </div>
    @endforeach

</body>

</html>
