<?php

namespace App\Http\Controllers\Api\AdminDashboard\ManajemenData;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\ManajemenData\KinerjaEkonomiManagementRequest;
use App\Jobs\AdminDashboard\ImportKinerjaEkonomiCsvJob;
use App\Jobs\AdminDashboard\PublishKinerjaEkonomiBatchJob;
use App\Jobs\AdminDashboard\ValidateKinerjaEkonomiBatchJob;
use App\Services\AdminDashboard\ManajemenData\KinerjaEkonomiManagementService;
use App\Support\SpreadsheetRowLimitReadFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class KinerjaEkonomiManagementController extends Controller
{
    private const ACCESS_ALL_PERMISSION = 'read_all_admin_kinerja_ekonomi';
    private const CURRENT_DATA_READ_PERMISSION = 'read_admin_kinerja_ekonomi_current';

    public function __construct(
        private readonly KinerjaEkonomiManagementService $service
    ) {
    }

    public function index(KinerjaEkonomiManagementRequest $request): JsonResponse
    {
        $validated = $this->scopedFilters($request, $request->validated());
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $batches = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batches fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary($validated),
                'items' => $batches->items(),
                'meta' => [
                    'page' => $batches->currentPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                    'last_page' => $batches->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(KinerjaEkonomiManagementRequest $request, string $id): JsonResponse
    {
        try {
            $batch = $this->service->findByIdentifier(
                $id,
                $this->scopedFilters($request, $request->validated())
            );
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch fetched successfully',
            'data' => $batch,
        ]);
    }

    public function currentIndex(KinerjaEkonomiManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'Tahun';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $rows = $this->service->paginateCurrentData($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi current rows fetched successfully',
            'data' => [
                'items' => $rows->items(),
                'meta' => [
                    'page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'last_page' => $rows->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function store(KinerjaEkonomiManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('file')) {
            $validated['source_type'] = 'upload';
            $validated['original_filename'] = $validated['original_filename']
                ?? $request->file('file')->getClientOriginalName();

            $path = $request->file('file')->store('imports/kinerja-ekonomi');
            $batch = $this->service->createQueuedUpload($validated, $this->userId($request));
            ImportKinerjaEkonomiCsvJob::dispatch($batch['id'], $path, $validated['column_mapping'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Kinerja ekonomi upload accepted and queued for background processing',
                'data' => $batch,
            ], 202);
        }

        if (empty($validated['rows'])) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu row kinerja ekonomi wajib dikirim.',
                'data' => null,
            ], 422);
        }

        $batch = $this->service->create($validated, $this->userId($request));

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch created successfully',
            'data' => $batch,
        ], 201);
    }

    public function previewUpload(KinerjaEkonomiManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');
        $sampleSize = (int) ($validated['sample_size'] ?? 8);
        $extension = strtolower($file->getClientOriginalExtension());
        $preview = in_array($extension, ['xlsx', 'xls'], true)
            ? $this->parseSpreadsheetPreview($file->getRealPath(), $sampleSize)
            : $this->parseCsvPreview($file->getRealPath(), $sampleSize);

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi upload preview fetched successfully',
            'data' => [
                'original_filename' => $file->getClientOriginalName(),
                ...$preview,
            ],
        ]);
    }

    public function updateRow(KinerjaEkonomiManagementRequest $request, string $id, int $rowId): JsonResponse
    {
        try {
            $this->assertBatchAccessible($request, $id);
            $batch = $this->service->updateRow($id, $rowId, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi row updated successfully',
            'data' => $batch,
        ]);
    }

    public function updateCurrentRow(KinerjaEkonomiManagementRequest $request, int $rowId): JsonResponse
    {
        try {
            $row = $this->service->updateCurrentDataRow($rowId, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi current row updated successfully',
            'data' => $row,
        ]);
    }

    public function deleteRow(string $id, int $rowId): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->deleteRow($id, $rowId);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi row deleted successfully',
            'data' => $batch,
        ]);
    }

    public function deleteCurrentRow(int $rowId): JsonResponse
    {
        try {
            $row = $this->service->deleteCurrentDataRow($rowId);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi current row deleted successfully',
            'data' => $row,
        ]);
    }

    public function deleteCurrentRows(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        try {
            $result = $this->service->deleteCurrentDataRows($validated['row_ids']);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi current rows deleted successfully',
            'data' => $result,
        ]);
    }

    public function deleteRows(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        try {
            $this->assertBatchAccessible($request, $id);
            $batch = $this->service->deleteRows($id, $validated['row_ids']);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi rows deleted successfully',
            'data' => $batch,
        ]);
    }

    public function clearStaging(string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->clearPublishedStagingRows($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi staging rows cleared successfully',
            'data' => $batch,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch deleted successfully',
            'data' => ['id' => $batch['id']],
        ]);
    }

    public function validateBatch(string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->markBatchValidating($id);
            ValidateKinerjaEkonomiBatchJob::dispatch($id, $this->userId(request()));
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch validation queued successfully',
            'data' => $batch,
        ], 202);
    }

    public function approve(string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->approve($id, $this->userId(request()));
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch approved successfully',
            'data' => $batch,
        ]);
    }

    public function publish(string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible(request(), $id);
            $batch = $this->service->markBatchPublishing($id);
            PublishKinerjaEkonomiBatchJob::dispatch($id, $this->userId(request()));
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch publish queued successfully',
            'data' => $batch,
        ], 202);
    }

    public function reject(KinerjaEkonomiManagementRequest $request, string $id): JsonResponse
    {
        try {
            $this->assertBatchAccessible($request, $id);
            $batch = $this->service->reject($id, $this->userId($request), $request->validated()['note'] ?? null);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi batch rejected successfully',
            'data' => $batch,
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Kinerja ekonomi options fetched successfully',
            'data' => $this->service->options(),
        ]);
    }

    private function parseCsvRows(string $path, array $mapping): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $mapping = $mapping ?: [
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

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $source = array_combine($headers, array_pad($line, count($headers), null));
            if ($source === false) {
                continue;
            }

            $row = [];
            foreach ($mapping as $target => $sourceColumn) {
                if ($sourceColumn !== null && $sourceColumn !== '' && array_key_exists($sourceColumn, $source)) {
                    $row[$target] = $source[$sourceColumn];
                }
            }

            if (array_filter($row, fn ($value) => $value !== null && $value !== '') !== []) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseCsvPreview(string $path, int $sampleSize): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [
                'headers' => [],
                'sample_rows' => [],
                'sample_size' => 0,
            ];
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $sampleRows = [];
        while (count($sampleRows) < $sampleSize && ($line = fgetcsv($handle)) !== false) {
            $source = array_combine($headers, array_pad($line, count($headers), null));
            if ($source !== false) {
                $sampleRows[] = $source;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'sample_size' => count($sampleRows),
        ];
    }

    private function parseSpreadsheetPreview(string $path, int $sampleSize): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new SpreadsheetRowLimitReadFilter($sampleSize + 1));
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestDataColumn();
        $headers = array_map(
            fn ($header) => trim((string) $header),
            $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? []
        );
        $sampleRows = [];

        for ($rowNumber = 2; $rowNumber <= $sampleSize + 1; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];
            if (array_filter($values, fn ($value) => $value !== null && $value !== '') === []) {
                continue;
            }

            $source = array_combine($headers, array_pad($values, count($headers), null));
            if ($source !== false) {
                $sampleRows[] = $source;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'sample_size' => count($sampleRows),
        ];
    }

    private function userId($request): ?int
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $user?->id;
    }

    private function scopedFilters($request, array $filters = []): array
    {
        if ($this->canAccessAllData($request)) {
            return $filters;
        }

        $userId = $this->userId($request);
        if ($userId !== null) {
            $filters['uploaded_by'] = $userId;
        }

        return $filters;
    }

    private function assertBatchAccessible($request, string $id): void
    {
        $this->service->findByIdentifier($id, $this->scopedFilters($request));
    }

    private function canAccessAllData($request): bool
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $user?->can(self::ACCESS_ALL_PERMISSION) === true;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Kinerja ekonomi batch not found',
            'data' => null,
        ], 404);
    }

    private function badRequest(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 400);
    }
}


