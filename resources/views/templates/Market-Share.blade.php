<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Market Share Snapshot</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      margin: 40px;
    }
    .header {
      background-color: #cce0f7;
      text-align: center;
      padding: 16px;
      font-size: 22px;
      font-weight: bold;
      text-transform: uppercase;
      border: 2px solid #000;
    }
    .sub-info {
      margin: 12px 0 20px;
      font-size: 12px;
      font-weight: bold;
    }
    h3 {
      text-align: center;
      margin: 5px 0 15px;
      font-weight: bold;
      font-size: 14px;
      line-height: 1.5;
    }
    h2 {
      text-align: center;
      margin-bottom: 8px;
      font-size: 15px;
      font-weight: bold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px;
    }
    th {
      background-color: #eee;
      text-align: center;
      font-weight: bold;
    }
    .total-row {
      background-color: #e74c3c;
      color: white;
      font-weight: bold;
    }
    .text-right {
      text-align: right;
    }
    .text-center {
      text-align: center;
    }
    .text-left {
      text-align: left;
    }
    .source {
      margin-top: 10px;
      text-align: center;
      color: #000;
      font-size: 11px;
      font-weight: normal;
    }
    .footer {
      text-align: center;
      margin-top: 40px;
      font-size: 11px;
    }
    .page-break {
      page-break-after: always;
    }
  </style>
</head>
<body>

  {{-- HEADER STATIC --}}
  <div class="header">
    <span>STAT</span> - <span style="font-weight: normal;">SNAPSHOTS</span>
  </div>

  <p class="sub-info">BSKLN: {{ \Carbon\Carbon::now()->locale('id')->isoFormat('D MMMM YYYY') }}</p>

  <h3>
    STAT-SNAPSHOTS MARKET SHARE<br>
    {{ $top_n }} PRODUK {{ strtoupper($status) }} UTAMA NEGARA {{ strtoupper($region) }} {{ $year }}
  </h3>

  {{-- LOOP SETIAP NEGARA --}}
  @foreach ($countries as $index => $country)
    <h2>{{ strtoupper($country['name']) }}</h2>

    <table>
      <tr>
        <th>No</th>
        <th>HS 4</th>
        <th>Nama Produk</th>
        <th>Nilai (ribu US$)</th>
        <th>Pangsa {{ strtoupper($status) }} (%)</th>
      </tr>
      <tr class="total-row">
        <td colspan="3" class="text-right">Total {{ strtoupper($status) }}</td>
        <td colspan="2" class="text-right">{{ number_format($country['total'], 0, ',', '.') }}</td>
      </tr>
      @foreach ($country['products'] as $i => $p)
        <tr>
          <td class="text-center">{{ $i + 1 }}</td>
          <td class="text-center">{{ $p['hs4'] }}</td>
          <td class="text-left">{{ $p['nama_produk'] }}</td>
          <td class="text-right">{{ number_format($p['nilai'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($p['pangsa'], 1, ',', '.') }}%</td>
        </tr>
      @endforeach
    </table>

    <div class="source">Sumber: {{ $sumber }}, diolah</div>

    <div class="footer">
      Badan Strategi Kebijakan Luar Negeri (BSKLN)<br>
      Kementerian Luar Negeri<br>
      Republik Indonesia
    </div>

    @if (!$loop->last)
      <div class="page-break"></div>
    @endif
  @endforeach

</body>
</html>
