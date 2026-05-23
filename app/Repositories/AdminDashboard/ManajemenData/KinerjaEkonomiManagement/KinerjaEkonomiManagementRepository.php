<?php

namespace App\Repositories\AdminDashboard\ManajemenData\KinerjaEkonomiManagement;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class KinerjaEkonomiManagementRepository implements KinerjaEkonomiManagementRepositoryInterface
{
    private const PUBLISH_CHUNK_SIZE = 1000;
    private const MAX_PER_PAGE = 100;

    private string $conn = 'server_mysql';
    private string $batchTable = 'data_import_batches';
    private string $stagingTable = 'tbkin_ekonomi_staging';
    private string $targetTable = 'tbkin_ekonomi_testing';
    private string $liveTable = 'tbkin_ekonomi_testing';
    private string $indicatorTable = 'tbindikator_kinek';
    private string $countryTable = 'tbnegara';
    private string $sourceTable = 'tbsumber';

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), self::MAX_PER_PAGE);
        $page = (int) ($filters['page'] ?? 1);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $query = DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('module', 'kinerja_ekonomi')
            ->where('target_table', $this->targetTable)
            ->when(
                array_key_exists('uploaded_by', $filters),
                fn ($query) => $query->where('uploaded_by', $filters['uploaded_by'])
            )
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('uuid', 'like', '%' . $search . '%')
                        ->orWhere('original_filename', 'like', '%' . $search . '%')
                        ->orWhere('note', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['source_type'] ?? null, fn ($query, string $sourceType) => $query->where('source_type', $sourceType));

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();
        $userNamesById = $this->userNamesById($rows->pluck('uploaded_by')->all());
        $items = $rows
            ->map(fn ($row) => $this->transformBatch($row, $userNamesById))
            ->all();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function paginateCurrentData(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), self::MAX_PER_PAGE);
        $page = (int) ($filters['page'] ?? 1);
        $sortBy = $filters['sort_by'] ?? 'Tahun';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $allowedSorts = ['ID', 'Kode_Alpha3', 'Bulan', 'Tahun', 'Nilai', 'Unit', 'Satuan', 'ID_Indikator', 'Komponen_Indikator', 'KodeSumber'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'Tahun';
        }

        $query = DB::connection($this->conn)
            ->table($this->liveTable . ' as ke')
            ->leftJoin($this->indicatorTable . ' as i', 'i.ID_Indikator', '=', 'ke.ID_Indikator')
            ->leftJoin($this->countryTable . ' as n', 'n.Kode_Alpha3', '=', 'ke.Kode_Alpha3')
            ->leftJoin($this->sourceTable . ' as s', 's.KodeSumber', '=', 'ke.KodeSumber')
            ->when(
                $filters['country_code'] ?? null,
                fn ($query, string $countryCode) => $query->where('ke.Kode_Alpha3', strtoupper($countryCode))
            )
            ->when(
                $filters['indicator_id'] ?? null,
                fn ($query, string $indicatorId) => $query->where('ke.ID_Indikator', $indicatorId)
            )
            ->when(
                $filters['source_code'] ?? null,
                fn ($query, string $sourceCode) => $query->where('ke.KodeSumber', strtoupper($sourceCode))
            )
            ->when(
                $filters['year'] ?? null,
                fn ($query, int|string $year) => $query->where('ke.Tahun', (int) $year)
            );

        $total = (clone $query)->count('ke.ID');
        $items = $query
            ->select(
                'ke.*',
                'i.Indikator as indikator',
                'n.Negara_IDN as negara',
                's.NamaSumber as sumber'
            )
            ->orderBy('ke.' . $sortBy, $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => $this->transformCurrentDataRow($row))
            ->all();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function getSummary(array $filters = []): array
    {
        $base = DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('module', 'kinerja_ekonomi')
            ->where('target_table', $this->targetTable)
            ->when(
                array_key_exists('uploaded_by', $filters),
                fn ($query) => $query->where('uploaded_by', $filters['uploaded_by'])
            );

        return [
            'total_batch' => (clone $base)->count(),
            'pending_batch' => (clone $base)->whereIn('status', ['draft', 'validating', 'valid', 'invalid'])->count(),
            'approved_batch' => (clone $base)->where('status', 'approved')->count(),
            'published_batch' => (clone $base)->where('status', 'published')->count(),
            'invalid_batch' => (clone $base)->where('status', 'invalid')->count(),
        ];
    }

    public function findBatchByIdentifier(string $identifier, array $rowFilters = []): array
    {
        $batch = $this->batchQuery($identifier, $rowFilters['uploaded_by'] ?? null)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        $page = (int) ($rowFilters['page'] ?? 1);
        $perPage = min(max((int) ($rowFilters['per_page'] ?? 25), 1), self::MAX_PER_PAGE);
        $sortBy = $rowFilters['sort_by'] ?? 'id';
        $sortDirection = $rowFilters['sort_direction'] ?? 'asc';
        $allowedSorts = ['id', 'Kode_Alpha3', 'Bulan', 'Tahun', 'Nilai', 'Unit', 'Satuan', 'ID_Indikator', 'Komponen_Indikator', 'KodeSumber', 'row_status', 'created_at', 'updated_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $rowsQuery = DB::connection($this->conn)
            ->table($this->stagingTable . ' as st')
            ->leftJoin($this->indicatorTable . ' as i', 'i.ID_Indikator', '=', 'st.ID_Indikator')
            ->leftJoin($this->countryTable . ' as n', 'n.Kode_Alpha3', '=', 'st.Kode_Alpha3')
            ->leftJoin($this->sourceTable . ' as s', 's.KodeSumber', '=', 'st.KodeSumber')
            ->where('st.batch_id', $batch->id)
            ->select(
                'st.*',
                'i.Indikator as indikator',
                'n.Negara_IDN as negara',
                's.NamaSumber as sumber'
            );

        $total = (clone $rowsQuery)->count();
        $rowsQuery = $sortBy === 'row_status'
            ? $rowsQuery->orderByRaw(
                "FIELD(st.row_status, 'invalid', 'pending', 'failed', 'valid', 'published') " . $sortDirection
            )->orderBy('st.id')
            : $rowsQuery->orderBy('st.' . $sortBy, $sortDirection);

        $rows = $rowsQuery
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => $this->transformRow($row))
            ->all();

        return [
            ...$this->transformBatch($batch),
            'rows' => [
                'items' => $rows,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / max($perPage, 1))),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ];
    }

    public function createBatch(array $data, ?int $userId = null): array
    {
        $rows = $data['rows'] ?? [];
        $now = now();

        return DB::connection($this->conn)->transaction(function () use ($data, $rows, $userId, $now) {
            $batchId = DB::connection($this->conn)
                ->table($this->batchTable)
                ->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'module' => 'kinerja_ekonomi',
                    'target_table' => $this->targetTable,
                    'source_type' => $data['source_type'] ?? 'manual',
                    'original_filename' => $data['original_filename'] ?? null,
                    'status' => 'draft',
                    'total_rows' => count($rows),
                    'valid_rows' => 0,
                    'invalid_rows' => 0,
                    'uploaded_by' => $userId,
                    'uploaded_at' => $now,
                    'note' => $data['note'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($rows as $row) {
                DB::connection($this->conn)
                    ->table($this->stagingTable)
                    ->insert($this->rowPayload($row, [
                        'batch_id' => $batchId,
                        'row_status' => 'pending',
                        'validation_errors' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
            }

            $batch = DB::connection($this->conn)->table($this->batchTable)->where('id', $batchId)->first();

            return $this->findBatchByIdentifier($batch->uuid);
        });
    }

    public function createQueuedUploadBatch(array $data, ?int $userId = null): array
    {
        $now = now();
        $batchId = DB::connection($this->conn)
            ->table($this->batchTable)
            ->insertGetId([
                'uuid' => (string) Str::uuid(),
                'module' => 'kinerja_ekonomi',
                'target_table' => $this->targetTable,
                'source_type' => 'upload',
                'original_filename' => $data['original_filename'] ?? null,
                'status' => 'validating',
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'uploaded_by' => $userId,
                'uploaded_at' => $now,
                'note' => $data['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $batch = DB::connection($this->conn)->table($this->batchTable)->where('id', $batchId)->first();

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function importFileIntoBatch(string $batchIdentifier, string $path, array $mapping): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->importSpreadsheetFileIntoBatch($batch, $path, $mapping);
        }

        return $this->importCsvFileIntoExistingBatch($batch, $path, $mapping);
    }

    private function importCsvFileIntoExistingBatch(object $batch, string $path, array $mapping): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            $this->refreshBatchCounts((int) $batch->id, 'draft');

            return $this->findBatchByIdentifier($batch->uuid);
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $mapping = $mapping ?: $this->defaultCsvMapping();
        $now = now();
        $buffer = [];

        DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
            'status' => 'validating',
            'updated_at' => $now,
        ]);
        DB::connection($this->conn)->table($this->stagingTable)->where('batch_id', $batch->id)->delete();

        try {
            while (($line = fgetcsv($handle)) !== false) {
                $source = array_combine($headers, array_pad($line, count($headers), null));
                if ($source === false) {
                    continue;
                }

                $row = $this->mappedCsvRow($source, $mapping);
                if (array_filter($row, fn ($value) => $value !== null && $value !== '') === []) {
                    continue;
                }

                $buffer[] = $this->rowPayload($row, [
                    'batch_id' => $batch->id,
                    'row_status' => 'pending',
                    'validation_errors' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (count($buffer) >= 500) {
                    DB::connection($this->conn)->table($this->stagingTable)->insert($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DB::connection($this->conn)->table($this->stagingTable)->insert($buffer);
            }
        } finally {
            fclose($handle);
        }

        $this->refreshBatchCounts((int) $batch->id, 'draft');

        return $this->findBatchByIdentifier($batch->uuid);
    }

    private function importSpreadsheetFileIntoBatch(object $batch, string $path, array $mapping): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headerValues = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($header) => trim((string) $header), $headerValues);
        $mapping = $mapping ?: $this->defaultCsvMapping();
        $now = now();
        $buffer = [];

        DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
            'status' => 'validating',
            'updated_at' => $now,
        ]);
        DB::connection($this->conn)->table($this->stagingTable)->where('batch_id', $batch->id)->delete();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];
            $source = array_combine($headers, array_pad($values, count($headers), null));
            if ($source === false) {
                continue;
            }

            $row = $this->mappedCsvRow($source, $mapping);
            if (array_filter($row, fn ($value) => $value !== null && $value !== '') === []) {
                continue;
            }

            $buffer[] = $this->rowPayload($row, [
                'batch_id' => $batch->id,
                'row_status' => 'pending',
                'validation_errors' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (count($buffer) >= 500) {
                DB::connection($this->conn)->table($this->stagingTable)->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::connection($this->conn)->table($this->stagingTable)->insert($buffer);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->refreshBatchCounts((int) $batch->id, 'draft');

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function markBatchFailed(string $batchIdentifier, string $message): void
    {
        $batch = $this->batchQuery($batchIdentifier)->first();
        if (!$batch) {
            return;
        }

        DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
            'status' => 'failed',
            'note' => trim(($batch->note ? $batch->note . "\n" : '') . 'Import failed: ' . $message),
            'updated_at' => now(),
        ]);
    }

    public function markBatchValidating(string $batchIdentifier): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if (in_array($batch->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Batch yang sudah approved/published tidak dapat divalidasi ulang.');
        }

        DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
            'status' => 'validating',
            'updated_at' => now(),
        ]);

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function markBatchPublishing(string $batchIdentifier): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if (!$this->canBePublished($batch)) {
            throw new RuntimeException('Batch hanya dapat dipublish setelah approved.');
        }

        DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
            'status' => 'publishing',
            'updated_at' => now(),
        ]);

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function updateRow(string $batchIdentifier, int $rowId, array $data): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if (in_array($batch->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Batch yang sudah approved/published tidak dapat diubah.');
        }

        $affected = DB::connection($this->conn)
            ->table($this->stagingTable)
            ->where('batch_id', $batch->id)
            ->where('id', $rowId)
            ->update($this->rowPayload($data, [
                'row_status' => 'pending',
                'validation_errors' => null,
                'updated_at' => now(),
            ]));

        if ($affected === 0) {
            throw (new ModelNotFoundException())->setModel($this->stagingTable, [$rowId]);
        }

        $this->refreshBatchCounts((int) $batch->id, 'draft');

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function updateCurrentDataRow(int $rowId, array $data): array
    {
        $exists = DB::connection($this->conn)
            ->table($this->liveTable)
            ->where('ID', $rowId)
            ->exists();

        if (!$exists) {
            throw (new ModelNotFoundException())->setModel($this->liveTable, [$rowId]);
        }

        DB::connection($this->conn)
            ->table($this->liveTable)
            ->where('ID', $rowId)
            ->update($this->liveRowPayload($data));

        return $this->findCurrentDataRow($rowId);
    }

    public function deleteRow(string $batchIdentifier, int $rowId): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if (in_array($batch->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Batch yang sudah approved/published tidak dapat diubah.');
        }

        $affected = DB::connection($this->conn)
            ->table($this->stagingTable)
            ->where('batch_id', $batch->id)
            ->where('id', $rowId)
            ->delete();

        if ($affected === 0) {
            throw (new ModelNotFoundException())->setModel($this->stagingTable, [$rowId]);
        }

        $this->refreshBatchCounts((int) $batch->id, 'draft');

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function deleteCurrentDataRow(int $rowId): array
    {
        $row = $this->findCurrentDataRow($rowId);

        $affected = DB::connection($this->conn)
            ->table($this->liveTable)
            ->where('ID', $rowId)
            ->delete();

        if ($affected === 0) {
            throw (new ModelNotFoundException())->setModel($this->liveTable, [$rowId]);
        }

        return $row;
    }

    public function deleteCurrentDataRows(array $rowIds): array
    {
        $ids = collect($rowIds)
            ->map(fn ($rowId) => (int) $rowId)
            ->filter(fn ($rowId) => $rowId > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw new RuntimeException('Minimal satu data aktif harus dipilih.');
        }

        $existingCount = DB::connection($this->conn)
            ->table($this->liveTable)
            ->whereIn('ID', $ids)
            ->count();

        if ($existingCount !== count($ids)) {
            throw (new ModelNotFoundException())->setModel($this->liveTable, $ids);
        }

        DB::connection($this->conn)
            ->table($this->liveTable)
            ->whereIn('ID', $ids)
            ->delete();

        return [
            'deleted_count' => count($ids),
            'row_ids' => $ids,
        ];
    }

    public function deleteRows(string $batchIdentifier, array $rowIds): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if (in_array($batch->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Batch yang sudah approved/published tidak dapat diubah.');
        }

        $ids = collect($rowIds)
            ->map(fn ($rowId) => (int) $rowId)
            ->filter(fn ($rowId) => $rowId > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new RuntimeException('Pilih minimal satu row untuk dihapus.');
        }

        $existingCount = DB::connection($this->conn)
            ->table($this->stagingTable)
            ->where('batch_id', $batch->id)
            ->whereIn('id', $ids->all())
            ->count();

        if ($existingCount !== $ids->count()) {
            throw (new ModelNotFoundException())->setModel($this->stagingTable, $ids->all());
        }

        DB::connection($this->conn)
            ->table($this->stagingTable)
            ->where('batch_id', $batch->id)
            ->whereIn('id', $ids->all())
            ->delete();

        $this->refreshBatchCounts((int) $batch->id, 'draft');

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function clearPublishedStagingRows(string $batchIdentifier): array
    {
        $batch = $this->batchQuery($batchIdentifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$batchIdentifier]);
        }

        if ($batch->status !== 'published') {
            throw new RuntimeException('Staging hanya dapat dibersihkan setelah batch dipublish.');
        }

        DB::connection($this->conn)
            ->table($this->stagingTable)
            ->where('batch_id', $batch->id)
            ->delete();

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function deleteBatch(string $identifier): array
    {
        $batch = $this->batchQuery($identifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        if ($batch->status === 'published') {
            throw new RuntimeException('Batch yang sudah published tidak dapat dihapus.');
        }

        DB::connection($this->conn)->transaction(function () use ($batch) {
            DB::connection($this->conn)->table($this->stagingTable)->where('batch_id', $batch->id)->delete();
            DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->delete();
        });

        return $this->transformBatch($batch);
    }

    public function validateBatch(string $identifier, ?int $userId = null): array
    {
        $batch = $this->batchQuery($identifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        if (in_array($batch->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Batch yang sudah approved/published tidak dapat divalidasi ulang.');
        }

        return DB::connection($this->conn)->transaction(function () use ($batch, $userId) {
            DB::connection($this->conn)->table($this->batchTable)->where('id', $batch->id)->update([
                'status' => 'validating',
                'updated_at' => now(),
            ]);

            $now = now();

            DB::connection($this->conn)
                ->table($this->stagingTable)
                ->where('batch_id', $batch->id)
                ->update([
                    'row_status' => DB::raw($this->validationStatusSql()),
                    'validation_errors' => DB::raw($this->validationErrorsSql()),
                    'updated_at' => $now,
                ]);

            $invalidRows = DB::connection($this->conn)
                ->table($this->stagingTable)
                ->where('batch_id', $batch->id)
                ->where('row_status', 'invalid')
                ->count();

            $this->refreshBatchCounts((int) $batch->id, $invalidRows > 0 ? 'invalid' : 'valid', [
                'validated_by' => $userId,
                'validated_at' => $now,
            ]);

            return $this->findBatchByIdentifier($batch->uuid);
        });
    }

    public function approveBatch(string $identifier, ?int $userId = null): array
    {
        $batch = $this->batchQuery($identifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        if ($batch->status !== 'valid') {
            throw new RuntimeException('Batch hanya dapat di-approve setelah status valid.');
        }

        DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('id', $batch->id)
            ->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function publishBatch(string $identifier, ?int $userId = null): array
    {
        $batch = $this->batchQuery($identifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        if ($batch->published_at !== null) {
            throw new RuntimeException('Batch yang sudah dipublish tidak dapat dipublish ulang.');
        }

        return DB::connection($this->conn)->transaction(function () use ($batch, $userId) {
            DB::connection($this->conn)
                ->table($this->stagingTable)
                ->where('batch_id', $batch->id)
                ->where('row_status', 'valid')
                ->orderBy('id')
                ->chunkById(self::PUBLISH_CHUNK_SIZE, function ($rows) {
                    $publishedAt = now();
                    $targetRows = [];
                    $stagingRowIds = [];

                    foreach ($rows as $row) {
                        $targetRows[] = [
                            'Kode_Alpha3' => $row->Kode_Alpha3,
                            'Bulan' => $row->Bulan,
                            'Tahun' => $row->Tahun,
                            'Nilai' => $row->Nilai,
                            'Unit' => $row->Unit,
                            'Satuan' => $row->Satuan,
                            'ID_Indikator' => $row->ID_Indikator,
                            'Komponen_Indikator' => $row->Komponen_Indikator,
                            'KodeSumber' => $row->KodeSumber,
                        ];
                        $stagingRowIds[] = (int) $row->id;
                    }

                    if ($targetRows !== []) {
                        DB::connection($this->conn)
                            ->table($this->targetTable)
                            ->insert($targetRows);
                    }

                    if ($stagingRowIds !== []) {
                        DB::connection($this->conn)
                            ->table($this->stagingTable)
                            ->whereIn('id', $stagingRowIds)
                            ->update([
                                'row_status' => 'published',
                                'updated_at' => $publishedAt,
                            ]);
                    }
                }, 'id');

            DB::connection($this->conn)
                ->table($this->batchTable)
                ->where('id', $batch->id)
                ->update([
                    'status' => 'published',
                    'published_by' => $userId,
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->findBatchByIdentifier($batch->uuid);
        });
    }

    private function canBePublished(object $batch): bool
    {
        return $batch->approved_at !== null && $batch->published_at === null;
    }

    public function rejectBatch(string $identifier, ?int $userId = null, ?string $note = null): array
    {
        $batch = $this->batchQuery($identifier)->first();

        if (!$batch) {
            throw (new ModelNotFoundException())->setModel($this->batchTable, [$identifier]);
        }

        if ($batch->status === 'published') {
            throw new RuntimeException('Batch yang sudah published tidak dapat direject.');
        }

        DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('id', $batch->id)
            ->update([
                'status' => 'rejected',
                'approved_by' => $userId,
                'approved_at' => now(),
                'note' => $note ?? $batch->note,
                'updated_at' => now(),
            ]);

        return $this->findBatchByIdentifier($batch->uuid);
    }

    public function options(): array
    {
        return [
            'indicators' => DB::connection($this->conn)
                ->table($this->indicatorTable)
                ->select('ID_Indikator as id', 'Indikator as label', 'status', 'order', 'is_yoy', 'KodeSumber')
                ->orderBy('Indikator')
                ->get()
                ->map(function ($row) {
                    $item = (array) $row;
                    $item['id'] = (string) $item['id'];

                    return $item;
                })
                ->all(),
            'countries' => DB::connection($this->conn)
                ->table($this->countryTable)
                ->select('Kode_Alpha3 as value', 'Negara_IDN as label')
                ->whereNotNull('Kode_Alpha3')
                ->orderBy('Negara_IDN')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'sources' => DB::connection($this->conn)
                ->table($this->sourceTable)
                ->select('KodeSumber as value', 'NamaSumber as label')
                ->orderBy('NamaSumber')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
        ];
    }

    private function validationStatusSql(): string
    {
        return 'CASE WHEN ' . $this->validationInvalidConditionSql() . " THEN 'invalid' ELSE 'valid' END";
    }

    private function validationErrorsSql(): string
    {
        $conditions = [
            ["TRIM(COALESCE(Kode_Alpha3, '')) = ''", '{"field":"Kode_Alpha3","message":"Kode negara wajib diisi."}'],
            ['Bulan IS NOT NULL AND Bulan <> "" AND (Bulan < 1 OR Bulan > 12)', '{"field":"Bulan","message":"Bulan harus antara 1 sampai 12."}'],
            ['(Tahun IS NULL OR Tahun < 1900 OR Tahun > ' . ((int) date('Y') + 5) . ')', '{"field":"Tahun","message":"Tahun tidak valid."}'],
            ['Nilai IS NULL', '{"field":"Nilai","message":"Nilai wajib numeric."}'],
            ["TRIM(COALESCE(Satuan, '')) = ''", '{"field":"Satuan","message":"Satuan wajib diisi."}'],
            ["TRIM(COALESCE(ID_Indikator, '')) = ''", '{"field":"ID_Indikator","message":"Indikator wajib diisi."}'],
            ["TRIM(COALESCE(KodeSumber, '')) = ''", '{"field":"KodeSumber","message":"Kode sumber wajib diisi."}'],
        ];

        $parts = array_map(
            fn (array $item) => "CASE WHEN {$item[0]} THEN '{$item[1]},' ELSE '' END",
            $conditions
        );

        return "CASE WHEN {$this->validationInvalidConditionSql()} THEN CONCAT('[', TRIM(TRAILING ',' FROM CONCAT(" . implode(', ', $parts) . ")), ']') ELSE NULL END";
    }

    private function validationInvalidConditionSql(): string
    {
        return implode(' OR ', [
            "TRIM(COALESCE(Kode_Alpha3, '')) = ''",
            'Bulan IS NOT NULL AND Bulan <> "" AND (Bulan < 1 OR Bulan > 12)',
            '(Tahun IS NULL OR Tahun < 1900 OR Tahun > ' . ((int) date('Y') + 5) . ')',
            'Nilai IS NULL',
            "TRIM(COALESCE(Satuan, '')) = ''",
            "TRIM(COALESCE(ID_Indikator, '')) = ''",
            "TRIM(COALESCE(KodeSumber, '')) = ''",
        ]);
    }

    private function exists(string $table, string $column, mixed $value): bool
    {
        return DB::connection($this->conn)->table($table)->where($column, $value)->exists();
    }

    private function batchQuery(string $identifier, ?int $uploadedBy = null)
    {
        $query = DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('module', 'kinerja_ekonomi')
            ->where('target_table', $this->targetTable)
            ->when(
                $uploadedBy !== null,
                fn ($query) => $query->where('uploaded_by', $uploadedBy)
            );

        return Str::isUuid($identifier)
            ? $query->where('uuid', $identifier)
            : $query->where('id', $identifier);
    }

    private function refreshBatchCounts(int $batchId, string $status, array $extra = []): void
    {
        $counts = DB::connection($this->conn)
            ->table($this->stagingTable)
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw("SUM(CASE WHEN row_status IN ('valid', 'published') THEN 1 ELSE 0 END) as valid_rows")
            ->selectRaw("SUM(CASE WHEN row_status = 'invalid' THEN 1 ELSE 0 END) as invalid_rows")
            ->where('batch_id', $batchId)
            ->first();

        DB::connection($this->conn)
            ->table($this->batchTable)
            ->where('id', $batchId)
            ->update([
                'status' => $status,
                'total_rows' => (int) $counts->total_rows,
                'valid_rows' => (int) $counts->valid_rows,
                'invalid_rows' => (int) $counts->invalid_rows,
                'updated_at' => now(),
                ...$extra,
            ]);
    }

    private function rowPayload(array $row, array $extra = []): array
    {
        return [
            'Kode_Alpha3' => strtoupper(trim((string) ($row['Kode_Alpha3'] ?? $row['kode_alpha3'] ?? ''))),
            'Bulan' => $this->nullableInt($row['Bulan'] ?? $row['bulan'] ?? null),
            'Tahun' => (int) ($row['Tahun'] ?? $row['tahun'] ?? 0),
            'Nilai' => $this->nullableDecimal($row['Nilai'] ?? $row['nilai'] ?? null),
            'Unit' => $this->nullableString($row['Unit'] ?? $row['unit'] ?? null),
            'Satuan' => $this->nullableString($row['Satuan'] ?? $row['satuan'] ?? null),
            'ID_Indikator' => $this->nullableString($row['ID_Indikator'] ?? $row['id_indikator'] ?? $row['indicator_id'] ?? null) ?? '',
            'Komponen_Indikator' => $this->nullableString($row['Komponen_Indikator'] ?? $row['komponen_indikator'] ?? null),
            'KodeSumber' => $this->nullableString($row['KodeSumber'] ?? $row['kode_sumber'] ?? null),
            ...$extra,
        ];
    }

    private function liveRowPayload(array $row): array
    {
        return [
            'Kode_Alpha3' => strtoupper(trim((string) ($row['Kode_Alpha3'] ?? $row['kode_alpha3'] ?? ''))),
            'Bulan' => $this->nullableInt($row['Bulan'] ?? $row['bulan'] ?? null),
            'Tahun' => (int) ($row['Tahun'] ?? $row['tahun'] ?? 0),
            'Nilai' => $this->nullableDecimal($row['Nilai'] ?? $row['nilai'] ?? null),
            'Unit' => $this->nullableString($row['Unit'] ?? $row['unit'] ?? null),
            'Satuan' => $this->nullableString($row['Satuan'] ?? $row['satuan'] ?? null),
            'ID_Indikator' => $this->nullableString($row['ID_Indikator'] ?? $row['id_indikator'] ?? $row['indicator_id'] ?? null) ?? '',
            'Komponen_Indikator' => $this->nullableString($row['Komponen_Indikator'] ?? $row['komponen_indikator'] ?? null),
            'KodeSumber' => $this->nullableString($row['KodeSumber'] ?? $row['kode_sumber'] ?? null),
        ];
    }

    private function defaultCsvMapping(): array
    {
        return [
            'Kode_Alpha3' => 'Kode_Alpha3',
            'Bulan' => 'Bulan',
            'Tahun' => 'Tahun',
            'Nilai' => 'Nilai',
            'Unit' => 'Unit',
            'Satuan' => 'Satuan',
            'ID_Indikator' => 'ID_Indikator',
            'Komponen_Indikator' => 'Komponen_Indikator',
            'KodeSumber' => 'KodeSumber',
        ];
    }

    private function mappedCsvRow(array $source, array $mapping): array
    {
        $row = [];
        foreach ($mapping as $target => $sourceColumn) {
            if ($sourceColumn !== null && $sourceColumn !== '' && array_key_exists($sourceColumn, $source)) {
                $row[$target] = $source[$sourceColumn];
            }
        }

        return $row;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) str_replace(',', '.', (string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function userNamesById(array $userIds): array
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->all();
    }

    private function transformBatch(object $row, array $userNamesById = []): array
    {
        $uploadedBy = $row->uploaded_by === null ? null : (int) $row->uploaded_by;
        $userNamesById = $userNamesById ?: $this->userNamesById([$uploadedBy]);

        return [
            'id' => $row->uuid,
            'module' => $row->module,
            'target_table' => $row->target_table,
            'source_type' => $row->source_type ?? null,
            'original_filename' => $row->original_filename,
            'status' => $row->status,
            'total_rows' => (int) $row->total_rows,
            'valid_rows' => (int) $row->valid_rows,
            'invalid_rows' => (int) $row->invalid_rows,
            'uploaded_by' => $uploadedBy,
            'uploaded_by_name' => $uploadedBy === null ? null : ($userNamesById[$uploadedBy] ?? null),
            'validated_by' => $row->validated_by === null ? null : (int) $row->validated_by,
            'approved_by' => $row->approved_by === null ? null : (int) $row->approved_by,
            'published_by' => $row->published_by === null ? null : (int) $row->published_by,
            'uploaded_at' => $this->formatDate($row->uploaded_at),
            'validated_at' => $this->formatDate($row->validated_at),
            'approved_at' => $this->formatDate($row->approved_at),
            'published_at' => $this->formatDate($row->published_at),
            'note' => $row->note,
            'created_at' => $this->formatDate($row->created_at),
            'updated_at' => $this->formatDate($row->updated_at),
        ];
    }

    private function transformRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'kode_alpha3' => $row->Kode_Alpha3,
            'negara' => $row->negara ?? null,
            'bulan' => $row->Bulan === null ? null : (int) $row->Bulan,
            'tahun' => (int) $row->Tahun,
            'nilai' => $row->Nilai === null ? null : (float) $row->Nilai,
            'unit' => $row->Unit,
            'satuan' => $row->Satuan,
            'id_indikator' => (string) $row->ID_Indikator,
            'indikator' => $row->indikator ?? null,
            'komponen_indikator' => $row->Komponen_Indikator,
            'kode_sumber' => $row->KodeSumber,
            'sumber' => $row->sumber ?? null,
            'row_status' => $row->row_status,
            'validation_errors' => $row->validation_errors ? json_decode($row->validation_errors, true) : null,
            'created_at' => $this->formatDate($row->created_at),
            'updated_at' => $this->formatDate($row->updated_at),
        ];
    }

    private function transformCurrentDataRow(object $row): array
    {
        return [
            'id' => (int) $row->ID,
            'kode_alpha3' => $row->Kode_Alpha3,
            'negara' => $row->negara ?? null,
            'bulan' => $row->Bulan === null ? null : (int) $row->Bulan,
            'tahun' => (int) $row->Tahun,
            'nilai' => $row->Nilai === null ? null : (float) $row->Nilai,
            'unit' => $row->Unit,
            'satuan' => $row->Satuan,
            'id_indikator' => (string) $row->ID_Indikator,
            'indikator' => $row->indikator ?? null,
            'komponen_indikator' => $row->Komponen_Indikator,
            'kode_sumber' => $row->KodeSumber,
            'sumber' => $row->sumber ?? null,
        ];
    }

    private function findCurrentDataRow(int $rowId): array
    {
        $row = DB::connection($this->conn)
            ->table($this->liveTable . ' as ke')
            ->leftJoin($this->indicatorTable . ' as i', 'i.ID_Indikator', '=', 'ke.ID_Indikator')
            ->leftJoin($this->countryTable . ' as n', 'n.Kode_Alpha3', '=', 'ke.Kode_Alpha3')
            ->leftJoin($this->sourceTable . ' as s', 's.KodeSumber', '=', 'ke.KodeSumber')
            ->where('ke.ID', $rowId)
            ->select(
                'ke.*',
                'i.Indikator as indikator',
                'n.Negara_IDN as negara',
                's.NamaSumber as sumber'
            )
            ->first();

        if (!$row) {
            throw (new ModelNotFoundException())->setModel($this->liveTable, [$rowId]);
        }

        return $this->transformCurrentDataRow($row);
    }

    private function formatDate($date): ?string
    {
        return $date ? Carbon::parse($date)->utc()->format('Y-m-d\\TH:i:s\\Z') : null;
    }
}
