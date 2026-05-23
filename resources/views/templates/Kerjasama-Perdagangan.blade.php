<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Snapshot Perdagangan</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
    .header {
      background-color: #cce0f7;
      border: 2px solid black;
      text-align: center;
      padding: 10px;
      font-size: 22px;
      font-weight: bold;
    }
    .sub-info {
      font-weight: bold;
      margin: 10px 0 20px;
    }
    h2, h3 {
      text-align: center;
      margin: 5px 0;
      font-weight: bold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid black;
      padding: 6px;
    }
    th {
      background-color: #003366;
      color: white;
      text-align: center;
    }
    .country-title {
      background-color: #b9de69;
      text-align: center;
      font-weight: bold;
      padding: 6px;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .source {
      text-align: center;
      font-size: 11px;
      margin-top: 10px;
    }
    .footer {
      text-align: center;
      font-size: 11px;
      margin-top: 40px;
    }
    .page-break {
      page-break-after: always;
    }
  </style>
</head>
<body>

  {{-- Header --}}
  <div class="header">STAT – SNAPSHOTS</div>

  <p class="sub-info">Portfolio Demo: {{ $date }}</p>

  <h3>STAT-SNAPSHOTS NILAI KERJASAMA PERDAGANGAN {{ strtoupper($originLabel) }} DENGAN {{ strtoupper($destLabel) }}</h3>
  <h3>NILAI PERDAGANGAN {{ strtoupper($originLabel) }} KE {{ strtoupper($destLabel) }}<br>{{ $year_start }} - {{ $year_end }}</h3>

  @foreach ($countries as $index => $country)
    <table>
      <tr>
        <th colspan="6" class="country-title">
          {{ strtoupper($country['origin']) }} - {{ strtoupper($country['destination']) }}
        </th>
      </tr>
      <tr>
        <th>No</th>
        <th>Tahun</th>
        <th>Ekspor</th>
        <th>Impor</th>
        <th>Neraca</th>
        <th>Total</th>
      </tr>
      @foreach ($country['periods'] as $i => $period)
        <tr>
          <td class="text-center">{{ $i + 1 }}</td>
          <td class="text-center">{{ $period['tahun'] }}</td>
          <td class="text-right">{{ number_format($period['ekspor'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($period['impor'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($period['neraca'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($period['total'], 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </table>

    <div class="source">Sumber: {{ $sumber }}, diolah</div>

    <div class="footer">
      Portfolio Demo Team<br>
      Strategy & Analytics Showcase<br>
      Sample Repository
    </div>

    @if (!$loop->last)
      <div class="page-break"></div>
    @endif
  @endforeach

</body>
</html>
