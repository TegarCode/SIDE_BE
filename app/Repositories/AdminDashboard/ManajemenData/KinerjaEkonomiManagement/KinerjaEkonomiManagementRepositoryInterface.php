<?php

namespace App\Repositories\AdminDashboard\ManajemenData\KinerjaEkonomiManagement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KinerjaEkonomiManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function paginateCurrentData(array $filters): LengthAwarePaginator;

    public function getSummary(array $filters = []): array;

    public function findBatchByIdentifier(string $identifier, array $rowFilters = []): array;

    public function createBatch(array $data, ?int $userId = null): array;

    public function createQueuedUploadBatch(array $data, ?int $userId = null): array;

    public function importFileIntoBatch(string $batchIdentifier, string $path, array $mapping): array;

    public function markBatchFailed(string $batchIdentifier, string $message): void;

    public function markBatchValidating(string $batchIdentifier): array;

    public function markBatchPublishing(string $batchIdentifier): array;

    public function updateRow(string $batchIdentifier, int $rowId, array $data): array;

    public function updateCurrentDataRow(int $rowId, array $data): array;

    public function deleteRow(string $batchIdentifier, int $rowId): array;

    public function deleteCurrentDataRow(int $rowId): array;

    public function deleteCurrentDataRows(array $rowIds): array;

    public function deleteRows(string $batchIdentifier, array $rowIds): array;

    public function clearPublishedStagingRows(string $batchIdentifier): array;

    public function deleteBatch(string $identifier): array;

    public function validateBatch(string $identifier, ?int $userId = null): array;

    public function approveBatch(string $identifier, ?int $userId = null): array;

    public function publishBatch(string $identifier, ?int $userId = null): array;

    public function rejectBatch(string $identifier, ?int $userId = null, ?string $note = null): array;

    public function options(): array;
}

