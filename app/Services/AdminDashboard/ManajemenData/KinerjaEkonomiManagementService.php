<?php

namespace App\Services\AdminDashboard\ManajemenData;

use App\Repositories\AdminDashboard\ManajemenData\KinerjaEkonomiManagement\KinerjaEkonomiManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KinerjaEkonomiManagementService
{
    public function __construct(
        private readonly KinerjaEkonomiManagementRepositoryInterface $repository
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function paginateCurrentData(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateCurrentData($filters);
    }

    public function getSummary(array $filters = []): array
    {
        return $this->repository->getSummary($filters);
    }

    public function findByIdentifier(string $identifier, array $rowFilters = []): array
    {
        return $this->repository->findBatchByIdentifier($identifier, $rowFilters);
    }

    public function create(array $data, ?int $userId = null): array
    {
        return $this->repository->createBatch($data, $userId);
    }

    public function createQueuedUpload(array $data, ?int $userId = null): array
    {
        return $this->repository->createQueuedUploadBatch($data, $userId);
    }

    public function importFileIntoBatch(string $batchIdentifier, string $path, array $mapping): array
    {
        return $this->repository->importFileIntoBatch($batchIdentifier, $path, $mapping);
    }

    public function markBatchFailed(string $batchIdentifier, string $message): void
    {
        $this->repository->markBatchFailed($batchIdentifier, $message);
    }

    public function markBatchValidating(string $batchIdentifier): array
    {
        return $this->repository->markBatchValidating($batchIdentifier);
    }

    public function markBatchPublishing(string $batchIdentifier): array
    {
        return $this->repository->markBatchPublishing($batchIdentifier);
    }

    public function updateRow(string $batchIdentifier, int $rowId, array $data): array
    {
        return $this->repository->updateRow($batchIdentifier, $rowId, $data);
    }

    public function updateCurrentDataRow(int $rowId, array $data): array
    {
        return $this->repository->updateCurrentDataRow($rowId, $data);
    }

    public function deleteRow(string $batchIdentifier, int $rowId): array
    {
        return $this->repository->deleteRow($batchIdentifier, $rowId);
    }

    public function deleteCurrentDataRow(int $rowId): array
    {
        return $this->repository->deleteCurrentDataRow($rowId);
    }

    public function deleteCurrentDataRows(array $rowIds): array
    {
        return $this->repository->deleteCurrentDataRows($rowIds);
    }

    public function deleteRows(string $batchIdentifier, array $rowIds): array
    {
        return $this->repository->deleteRows($batchIdentifier, $rowIds);
    }

    public function clearPublishedStagingRows(string $batchIdentifier): array
    {
        return $this->repository->clearPublishedStagingRows($batchIdentifier);
    }

    public function delete(string $identifier): array
    {
        return $this->repository->deleteBatch($identifier);
    }

    public function validate(string $identifier, ?int $userId = null): array
    {
        return $this->repository->validateBatch($identifier, $userId);
    }

    public function approve(string $identifier, ?int $userId = null): array
    {
        return $this->repository->approveBatch($identifier, $userId);
    }

    public function publish(string $identifier, ?int $userId = null): array
    {
        return $this->repository->publishBatch($identifier, $userId);
    }

    public function reject(string $identifier, ?int $userId = null, ?string $note = null): array
    {
        return $this->repository->rejectBatch($identifier, $userId, $note);
    }

    public function options(): array
    {
        return $this->repository->options();
    }
}




