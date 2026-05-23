<?php

namespace App\Repositories\ChatBot;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatBotRepository implements ChatBotRepositoryInterface
{
  public function processMessage(string $message, ?string $sector = null, ?string $sectorHint = null): array
  {
    // 0) Validasi apakah pertanyaan masih relevan dengan domain data SIDE
    if (!$this->isValidDomainQuestion($message)) {
      // Pertanyaan terlalu umum / tidak terkait data → langsung "Perintah tidak dikenal"
      return $this->unknownCommandResponse();
    }

    // Resolve sektor (hint > param > keyword)
    $sector = $this->resolveSector($message, $sector, $sectorHint);
    $tableName = $this->getSectorTable($sector);

    // ❌ Sektor tidak dikenali → anggap "perintah tidak dikenal"
    if (!$sector || !$tableName) {
      return $this->unknownCommandResponse();
    }

    // $sql = $this->askOpenRouter($message, $sector);
    $sql = $this->askGroq($message, $sector);

    // Gagal bentuk SQL dari AI → perintah tidak dikenal
    if (!$sql || !is_string($sql)) {
      return $this->unknownCommandResponse();
    }

    $sql = $this->cleanSqlFromAi($sql);

    if ($sector === 'Perdagangan') {
      // 1) Inject alias & JOIN tanpa mengubah SELECT buatan AI
      $sql = preg_replace(
        '/\bfrom\s+`?tbtrade`?(?:\s+as\s+\w+)?\b/i',
        "FROM `tbtrade` AS t LEFT JOIN `tbharmonized` AS h ON t.HsCode = h.hscode",
        $sql,
        1
      );

      // 2) Qualify kolom tbtrade → t.<col> (biar WHERE/ORDER/GROUP aman)
      $sql = $this->qualifyColumns($sql, 't', [
        'Status',
        'HsCode',
        'Kode_Alpha3_Reporter',
        'Kode_Alpha3_Partner',
        'Tahun',
        'Nilai',
        'Vol',
        'Satuan',
        'Kode_Sumber',
        'ID_Sektor',
        'Bulan',
        'Kota_Reporter',
        'Kota_Partner',
        'Provinsi_Partner',
        'Provinsi_Reporter',
      ]);

      // 3) Perbaikan alias:
      //    a) "AS t.Nilai" → "AS Nilai"
      $sql = preg_replace('/\bAS\s+t\.\s*`?([A-Za-z0-9_]+)`?/i', 'AS $1', $sql);
      //    b) "SUM(t.Nilai) t.NilaiTotal" (alias tanpa AS) → "SUM(t.Nilai) NilaiTotal"
      $sql = preg_replace('/(?<=\))\s+t\.\s*([A-Za-z0-9_]+)/i', ' $1', $sql);
    }

    // Jika bukan SELECT → perintah tidak dikenal
    if (!str_starts_with(strtolower(ltrim($sql)), 'select')) {
      return $this->unknownCommandResponse();
    }

    // Jika tabel utama bukan tabel sektor yang diizinkan → anggap saja tidak ada data
    if (!preg_match("/\bfrom\s+(?:`)?$tableName(?:`)?\b/i", $sql)) {
      return $this->noDataResponse();
    }

    // Validasi HSCode khusus Perdagangan
    if ($sector === 'Perdagangan' && preg_match("/where.*hscode.*(['\"])(\d+)\1/i", $sql, $m)) {
      $hsCode = $m[2] ?? '';
      if (strlen($hsCode) > 4) {
        return ['answer' => '⚠️ Kode HS maksimal terdiri dari 4 digit.'];
      }
    }

    // Tambah filter sumber data per sektor (kecuali Jasa)
    $sql = $this->applySourceFilter($sql, $sector);

    try {
      $results = DB::connection('server_mysql')->select($sql);

      // Tidak ada data → pesan seragam
      if (!is_array($results) || empty($results)) {
        return $this->noDataResponse();
      }

      $excludedCols = $this->getSectorColumnsToExclude($sector);

      $output = collect($results)->map(function ($row, $i) use ($excludedCols, $sector) {
        $data = json_decode(json_encode($row), true);
        $lines = ["Data #" . ($i + 1)];

        foreach ($data as $col => $val) {
          if (in_array($col, $excludedCols, true)) continue;

          $normalizedCol = $this->normalizeValueFieldName($col);

          switch ($normalizedCol) {
            case 'Nilai':
              $label = 'Nilai';
              $formatted = is_numeric($val)
                ? ($sector === 'Pariwisata' || $sector === 'Jasa'
                  ? number_format((float)$val, 0, ',', '.')
                  : '$' . number_format((float)$val, 2, ',', '.'))
                : (string)$val;
              break;

            case 'HsCode':
              $label = 'HSCode';
              $desc = $this->getHsDescription($val);
              $formatted = $val . ($desc ? " – $desc" : '');
              break;

            case 'Kode_Alpha3_Reporter':
            case 'Kode_Alpha3_Asal':
              $label = 'Negara Asal';
              $formatted = $this->getCountryName($val) ?: (string)$val;
              break;

            case 'Kode_Alpha3_Partner':
            case 'Kode_Alpha3_Tujuan':
              $label = 'Negara Tujuan';
              $formatted = $this->getCountryName($val) ?: (string)$val;
              break;

            case 'Tahun':
              $label = 'Tahun';
              $formatted = (string)$val;
              break;

            case 'Jenis_Kelamin':
              $label = 'Jenis Kelamin';
              $formatted = match (strtoupper((string)$val)) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => (string)$val,
              };
              break;

            case 'ID_Profesi':
              $label = 'ID Profesi';
              $desc = $this->getProfesi($val);
              $formatted = $desc ? "$val - $desc" : (string)$val;
              break;

            case 'Profesi':
              $label = 'Profesi';
              $formatted = (string)$val;
              break;

            default:
              $label = ucwords(str_replace('_', ' ', $col));
              $formatted = is_numeric($val)
                ? number_format((float)$val, 0, ',', '.')
                : (string)$val;
              break;
          }

          $lines[] = sprintf("%-20s: %s", $label, $formatted);
        }

        return implode("\n", $lines);
      })->implode("\n\n" . str_repeat('-', 30) . "\n\n");

      $intro = $this->askGroqIntro($output, $sector, $sql);

      // Susun teks jawaban + info sumber
      $sourceLabel = $this->getSectorSourceLabel($sector);
      $answerText  = $intro . "\n\n" . $output;

      if ($sourceLabel) {
        $answerText .= "\n\nSumber data utama: " . $sourceLabel . '.';
      }

      $rawData = collect($results)->map(fn($row) => (array)$row)->values()->toArray();
      $isVisualizable = $this->hasKeyColumn($results);

      return [
        'answer'        => $answerText,
        'visualization' => $isVisualizable,
        'dataset'       => $isVisualizable ? $rawData : null,
      ];
    } catch (\Exception $e) {
      // Log detail ke server, tapi ke user tetap pesan netral
      Log::error('ChatBotRepository processMessage error', [
        'error'  => $e->getMessage(),
        'sector' => $sector,
        'sql'    => $sql ?? null,
      ]);

      // Jangan lagi pakai "Terjadi kesalahan saat menghubungi server"
      // → tampilkan pesan seragam yang tidak menyalahkan server
      return $this->noDataResponse();
    }
  }

  /**
   * Menentukan apakah pertanyaan user masih relevan dengan domain data
   * (perdagangan, pariwisata, investasi, jasa).
   *
   * Jika tidak ada satu pun kata kunci yang cocok, dianggap "perintah tidak dikenal".
   */
  private function isValidDomainQuestion(string $message): bool
  {
    $lower = mb_strtolower($message);

    // Kata kunci global yang menunjukkan konteks data SIDE
    $keywords = [
      // Perdagangan
      'perdagangan', 'dagang', 'ekspor', 'export', 'impor', 'import',
      'trade', 'hs', 'hscode', 'komoditas', 'tarif',

      // Pariwisata
      'pariwisata', 'wisata', 'tourism', 'wisman', 'kunjungan', 'hotel', 'akomodasi',

      // Investasi
      'investasi', 'investment', 'pma', 'pmdn', 'fdi', 'penanaman modal',

      // Jasa
      'jasa', 'services', 'profesi', 'tenaga kerja',

      // Konteks umum data
      'negara', 'asal', 'tujuan', 'partner', 'reporter',
      'nilai', 'volume', 'vol', 'top', 'terbesar', 'terkecil',
      'tren', 'trend', 'pertumbuhan', 'growth',
      'periode', 'tahun', 'rentang tahun',
    ];

    foreach ($keywords as $w) {
      if (str_contains($lower, $w)) {
        return true;
      }
    }

    // Optional: kalau ada pola tahun (misal 2010–2049) boleh dianggap relevan
    if (preg_match('/\b(19[5-9]\d|20[0-4]\d)\b/', $lower)) {
      return true;
    }

    return false;
  }

  private function resolveSector(string $message, ?string $sector, ?string $sectorHint): ?string
  {
    if ($sectorHint && $this->getSectorTable($sectorHint)) return $sectorHint;
    if ($sector && $this->getSectorTable($sector)) return $sector;

    $map = [
      'Perdagangan' => ['perdagangan','dagang','ekspor','export','impor','import','hs','hscode','komoditas','tarif','trade'],
      'Pariwisata'  => ['pariwisata','wisata','tourism','kunjungan','wisman','hotel','akomodasi'],
      'Investasi'   => ['investasi','investment','pma','pmdn','fdi','inbound','outbound','penanaman modal'],
      'Jasa'        => ['jasa','services','ekspor jasa','impor jasa','jasa keuangan','jasa telekom','profesi'],
    ];
    $lower = mb_strtolower($message);
    foreach ($map as $sec => $keywords) {
      foreach ($keywords as $w) {
        if (str_contains($lower, $w)) return $sec;
      }
    }
    return null;
  }

  private function normalizeValueFieldName(string $col): string
  {
    $normalized = [
      'Nilai'                     => 'Nilai',
      'SUM(Nilai)'               => 'Nilai',
      'Jumlah_Wisatawan'         => 'Nilai',
      'Total_Wisatawan'          => 'Nilai',
      'Nilai_Investasi'          => 'Nilai',
      'Total_Investasi'          => 'Nilai',
      'Total_Investasi_Outbound' => 'Nilai',
      'Total_Investasi_Inbound'  => 'Nilai',
      'Jumlah'                   => 'Nilai',
      'Total_Jumlah'             => 'Nilai',
    ];
    return $normalized[$col] ?? $col;
  }

  private function hasKeyColumn(array $rows, array $targetColumns = [
    'Nilai','Jumlah','Nilai_Investasi','Total_Jumlah','Total_Wisatawan','Total_Investasi','Total_Investasi_Outbound','Total_Investasi_Inbound'
  ]): bool
  {
    if (empty($rows)) return false;
    $sample = (array)$rows[0];
    $normalizedKeys = array_map([$this, 'normalizeValueFieldName'], array_keys($sample));
    if (in_array('Nilai', $normalizedKeys, true)) return true;
    foreach ($targetColumns as $col) if (array_key_exists($col, $sample)) return true;
    return false;
  }

  private function askGroq(string $message, string $sector): ?string
  {
    $systemPrompt = match ($sector) {
      'Perdagangan' => implode("\n", [
        "Anda adalah asisten SQL. Kembalikan **hanya** query SELECT SQL (tanpa penjelasan).",
        "Tabel: `tbtrade`. Kolom: Status, HsCode, Kode_Alpha3_Reporter, Kode_Alpha3_Partner, Tahun, Nilai.",
        "Aturan FILTER: hanya terapkan filter yang **secara eksplisit** disebut user (Status/Export/Import, Tahun/rentang tahun, Kode_Alpha3_Reporter, Kode_Alpha3_Partner, HsCode, dll).",
        "Jangan menambahkan filter default seperti `Kode_Alpha3_Reporter='IDN'` jika tidak disebut.",
        "Aturan TOTAL: jika user menyebut kata kunci total/akumulasi/kumulatif/keseluruhan/agregat (atau 'totalnya'): gunakan `SELECT SUM(Nilai) AS Nilai` dan **jangan** pakai GROUP BY kecuali user jelas meminta pengelompokan (mis. per tahun/per negara/per HS).",
        "Jika user minta **tren/per tahun**: gunakan `SUM(Nilai) AS Nilai` + `GROUP BY Tahun` (dan kolom lain yang diminta, mis. Kode_Alpha3_Partner untuk 'per negara').",
        "Jika user minta **Top N** (contoh: top 5/10 besar): gunakan `ORDER BY SUM(Nilai) DESC` lalu `LIMIT N`. Sertakan kolom pengelompokan yang relevan (negara/HS/tahun) sesuai permintaan.",
        "HS Code: jika user memberi HSCode, gunakan apa adanya; jangan menambah atau memotong digit. Hindari penggunaan fungsi yang mengubah kode kecuali user meminta level HS (mis. HS2/HS4/HS6).",
        "Output harus query SQL murni untuk `tbtrade`, tanpa komentar, tanpa teks lain.",
      ]),
      'Pariwisata' => implode("\n", [
        "Anda adalah asisten SQL. Kembalikan **hanya** query SELECT SQL (tanpa penjelasan).",
        "Tabel: `tbtourism`. Kolom: Tahun, Jumlah_Wisatawan, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan.",
        "Filter hanya yang disebut user (asal/tujuan/tahun/rentang, dll).",
        "Kata kunci total/akumulasi/kumulatif: gunakan `SUM(Jumlah_Wisatawan) AS Jumlah_Wisatawan` tanpa GROUP BY kecuali user minta pengelompokan (mis. per tahun/per negara).",
        "Jika minta tren/per tahun: `SUM(Jumlah_Wisatawan)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Jumlah_Wisatawan) DESC` + `LIMIT N` dengan kolom grup sesuai permintaan.",
        "SQL murni saja.",
      ]),
      'Investasi' => implode("\n", [
        "Anda adalah asisten SQL. Kembalikan **hanya** query SELECT SQL (tanpa penjelasan).",
        "Tabel: `tbinvestment`. Kolom: Tahun, Nilai_Investasi, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan, Status (Outbound/Inbound).",
        "Filter hanya yang disebut user (Status/tahun/asal/tujuan, dll).",
        "Kata kunci total/akumulasi/kumulatif: `SUM(Nilai_Investasi) AS Nilai_Investasi` tanpa GROUP BY kecuali diminta.",
        "Tren/per tahun: `SUM(Nilai_Investasi)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Nilai_Investasi) DESC` + `LIMIT N` dengan kolom grup relevan.",
        "SQL murni saja.",
      ]),
      'Jasa' => implode("\n", [
        "Anda adalah asisten SQL. Kembalikan **hanya** query SELECT SQL (tanpa penjelasan).",
        "Tabel: `tbservices` JOIN `tbprofesi` via `ID_Profesi` untuk kolom `Profesi`.",
        "Kolom: Tahun, Jumlah, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan, Jenis_Kelamin, Profesi.",
        "Filter hanya yang disebut user (asal/tujuan/tahun/profesi/jenis kelamin, dll).",
        "Kata kunci total/akumulasi/kumulatif: `SUM(Jumlah) AS Jumlah` tanpa GROUP BY kecuali diminta.",
        "Tren/per tahun: `SUM(Jumlah)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Jumlah) DESC` + `LIMIT N` dengan kolom grup relevan.",
        "SQL murni saja.",
      ]),
      default => "Kembalikan hanya SELECT SQL sederhana dari tabel yang sesuai, terapkan filter yang eksplisit disebut user.",
    };

    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
      'Content-Type'  => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [
      'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
      'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $message]
      ],
      'temperature' => 0.2,
    ]);

    if (!$response->successful()) {
      return null;
    }

    return $response->json('choices.0.message.content') ?? null;
  }

  private function askOpenRouter(string $message, string $sector): ?string
  {
    $systemPrompt = match ($sector) {
      'Perdagangan' => implode("\n", [
        "Anda adalah asisten SQL. Balas **hanya** dengan SELECT SQL untuk `tbtrade`.",
        "Kolom: Status, HsCode, Kode_Alpha3_Reporter, Kode_Alpha3_Partner, Tahun, Nilai.",
        "Terapkan **hanya filter yang disebut user**. Jangan menambah filter default.",
        "Jika user sebut total/akumulasi/kumulatif/keseluruhan/agregat: gunakan `SUM(Nilai) AS Nilai` dan **tanpa GROUP BY**, kecuali user meminta pengelompokan (per tahun/per negara/per HS).",
        "Jika minta tren/per tahun: `SUM(Nilai) AS Nilai` + `GROUP BY Tahun` (+ kolom lain jika diminta).",
        "Jika minta Top N: `ORDER BY SUM(Nilai) DESC` + `LIMIT N` dan tampilkan kolom pengelompokan yang diminta.",
        "HSCode gunakan sesuai yang ditulis user; jangan ubah panjangnya kecuali user minta level HS tertentu.",
        "Kembalikan SQL murni (tanpa teks lain).",
      ]),
      'Pariwisata' => implode("\n", [
        "Balas **hanya** SELECT SQL untuk `tbtourism`.",
        "Kolom: Tahun, Jumlah_Wisatawan, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan.",
        "Filter hanya yang disebut user.",
        "Total: `SUM(Jumlah_Wisatawan) AS Jumlah_Wisatawan` tanpa GROUP BY kecuali diminta.",
        "Tren/per tahun: `SUM(Jumlah_Wisatawan)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Jumlah_Wisatawan) DESC` + `LIMIT N` dengan kolom grup relevan.",
        "SQL murni saja.",
      ]),
      'Investasi' => implode("\n", [
        "Balas **hanya** SELECT SQL untuk `tbinvestment`.",
        "Kolom: Tahun, Nilai_Investasi, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan, Status.",
        "Filter hanya yang disebut user.",
        "Total: `SUM(Nilai_Investasi) AS Nilai_Investasi` tanpa GROUP BY kecuali diminta.",
        "Tren/per tahun: `SUM(Nilai_Investasi)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Nilai_Investasi) DESC` + `LIMIT N`.",
        "SQL murni saja.",
      ]),
      'Jasa' => implode("\n", [
        "Balas **hanya** SELECT SQL untuk `tbservices` JOIN `tbprofesi` via `ID_Profesi`.",
        "Kolom: Tahun, Jumlah, Kode_Alpha3_Asal, Kode_Alpha3_Tujuan, Jenis_Kelamin, Profesi.",
        "Filter hanya yang disebut user.",
        "Total: `SUM(Jumlah) AS Jumlah` tanpa GROUP BY kecuali diminta.",
        "Tren/per tahun: `SUM(Jumlah)` + `GROUP BY Tahun`.",
        "Top N: `ORDER BY SUM(Jumlah) DESC` + `LIMIT N`.",
        "SQL murni saja.",
      ]),
      default => "Kembalikan SELECT SQL sederhana; terapkan hanya filter yang eksplisit disebut user; tanpa teks lain.",
    };

    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
      'Content-Type'  => 'application/json',
    ])->post('https://openrouter.ai/api/v1/chat/completions', [
      'model' => 'openai/gpt-4o',
      'max_tokens' => 800,
      'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $message],
      ],
    ]);

    if (!$response->successful()) {
      return null;
    }

    return $response->json('choices.0.message.content') ?? null;
  }

  private function askGroqIntro(string $output, string $sector, string $sql): string
  {
    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
      'Content-Type'  => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [
      'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
      'messages' => [
        [
          'role' => 'system',
          'content' => "Buatlah penjelasan singkat, sopan, dan netral (maksimal 2 kalimat) berdasarkan hasil sektor $sector berikut ini. Jangan rangkum atau ulangi isi datanya. Untuk sektor seperti Pariwisata, gunakan satuan jumlah tanpa simbol dolar atau juta. Soroti tren jika terlihat. Tegaskan bahwa kesimpulan dibuat berdasarkan data yang tersedia saat ini dan bisa berubah di masa mendatang."
        ],
        ['role' => 'user', 'content' => $output . "\n\nSQL:\n" . $sql]
      ],
      'temperature' => 0.5,
    ]);

    return trim($response->json('choices.0.message.content') ?? '📊 Berikut ini hasil analisis berdasarkan permintaan Anda.');
  }

  private function cleanSqlFromAi(string $sql): string
  {
    $sql = preg_replace('/```sql|```/i', '', $sql);
    $sql = trim($sql);
    $sql = str_ireplace(["'ekspor'", "'Ekspor'"], "'Export'", $sql);
    $sql = str_ireplace(["'impor'", "'Impor'"], "'Import'", $sql);
    if (!preg_match('/\blimit\s+\d+\b/i', $sql)) {
      $sql = rtrim($sql, ";\r\n\t ") . ' LIMIT 10;';
    } elseif (!str_ends_with(trim($sql), ';')) {
      $sql .= ';';
    }
    return $sql;
  }

  private function getSectorTable(string $sector): ?string
  {
    return match ($sector) {
      'Perdagangan' => 'tbtrade',
      'Pariwisata'  => 'tbtourism',
      'Investasi'   => 'tbinvestment',
      'Jasa'        => 'tbservices',
      default       => null,
    };
  }

  private function getSectorColumnsToExclude(string $sector): array
  {
    return match ($sector) {
      'Perdagangan' => ['ID', 'Tarif', 'Kode_Sumber', 'ID_Sektor', 'Bulan', 'Vol', 'Satuan', 'Kota_Reporter', 'Kota_Partner', 'Provinsi_Partner', 'Provinsi_Reporter', 'hs_description'],
      'Pariwisata'  => ['ID', 'Kode_Sumber'],
      'Investasi'   => ['ID', 'Kode_Sumber'],
      'Jasa'        => ['ID', 'Kode_Sumber'],
      default       => [],
    };
  }

  // 🔹 label sumber data per sektor (untuk ditampilkan di jawaban)
  private function getSectorSourceLabel(string $sector): ?string
  {
    return match ($sector) {
      'Perdagangan' => 'Trademap',
      'Pariwisata'  => 'Badan Pusat Statistik (BPS)',
      'Investasi'   => 'BKPM',
      // Jasa belum punya label sumber baku
      default       => null,
    };
  }

  private function getCountryName(?string $kodeAlpha3): ?string
  {
    if (!$kodeAlpha3) return null;
    return Cache::remember("negara_$kodeAlpha3", 3600, function () use ($kodeAlpha3) {
      return DB::connection('server_mysql')
        ->table('tbnegara')
        ->where('Kode_Alpha3', strtoupper($kodeAlpha3))
        ->value('Negara_IDN');
    });
  }

  private function getHsDescription(?string $hscode): ?string
  {
    if (!$hscode) return null;
    return Cache::remember("desc_$hscode", 3600, function () use ($hscode) {
      return DB::connection('server_mysql')
        ->table('tbharmonized')
        ->where('hscode', $hscode)
        ->value('description');
    });
  }

  private function getProfesi(?string $idprofesi): ?string
  {
    if (!$idprofesi) return null;
    return Cache::remember("profesi_$idprofesi", 3600, function () use ($idprofesi) {
      return DB::connection('server_mysql')
        ->table('tbprofesi')
        ->where('ID_Profesi', $idprofesi)
        ->value('Profesi') ?? 'Tidak diketahui';
    });
  }

  private function qualifyColumns(string $sql, string $alias, array $columns): string
  {
    foreach ($columns as $col) {
      $pattern = '/(?<![A-Za-z0-9_\.])`?' . preg_quote($col, '/') . '`?(?![A-Za-z0-9_\.])/i';
      $sql = preg_replace_callback($pattern, function ($m) use ($alias, $col) {
        return $alias . '.' . $col;
      }, $sql);
    }
    return $sql;
  }

  /**
   * Tambahkan filter sumber per sektor:
   * - Perdagangan (tbtrade)   : t.Kode_Sumber = 5 (Trademap)
   * - Pariwisata (tbtourism)  : Kode_Sumber = 1 (BPS)
   * - Investasi (tbinvestment): Kode_Sumber = 6 (BKPM)
   * - Jasa (tbservices)       : tidak difilter
   */
  private function applySourceFilter(string $sql, string $sector): string
  {
    // Kalau bukan sektor yang butuh filter sumber → langsung return
    if (!in_array($sector, ['Perdagangan', 'Pariwisata', 'Investasi'], true)) {
      return $sql;
    }

    // Kalau sudah ada filter Kode_Sumber di query AI → jangan tumpuk
    if (preg_match('/kode_sumber\s*=/i', $sql)) {
      return $sql;
    }

    // Tentukan kondisi per sektor
    $condition = match ($sector) {
      'Perdagangan' => 't.Kode_Sumber = 5',  // Trademap
      'Pariwisata'  => 'Kode_Sumber = 1',    // BPS
      'Investasi'   => 'Kode_Sumber = 6',    // BKPM
      default       => null,
    };

    if (!$condition) {
      return $sql;
    }

    $hasWhere = preg_match('/\bwhere\b/i', $sql);

    if ($hasWhere) {
      // Sisipkan setelah WHERE: WHERE <cond> AND ...
      return preg_replace('/\bwhere\b/i', 'WHERE ' . $condition . ' AND', $sql, 1);
    }

    // Tidak ada WHERE → sisipkan sebelum GROUP BY / ORDER BY / LIMIT
    $pattern = '/\b(group\s+by|order\s+by|limit)\b/i';
    if (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE)) {
      $pos    = $m[0][1];
      $before = substr($sql, 0, $pos);
      $after  = substr($sql, $pos);
      return rtrim($before, " \t\n\r;") . ' WHERE ' . $condition . ' ' . $after;
    }

    // Tidak ada GROUP BY / ORDER BY / LIMIT → tambahkan di ujung sebelum ';'
    $sqlTrim = rtrim($sql, " \t\n\r;");
    return $sqlTrim . ' WHERE ' . $condition . ';';
  }

  /* ===================== Helper pesan seragam ===================== */

  /** Prompt/perintah tidak dikenali oleh sistem/chatbot */
  private function unknownCommandResponse(): array
  {
    return ['answer' => 'Perintah tidak dikenal.'];
  }

  /** SQL valid tapi tidak ada hasil, atau error di backend yang ingin disamarkan ke user */
  private function noDataResponse(): array
  {
    return ['answer' => 'Tidak ada data ditemukan.'];
  }
}
