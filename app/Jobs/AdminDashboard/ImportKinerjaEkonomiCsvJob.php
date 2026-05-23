<?php

namespace App\Jobs\AdminDashboard;

use App\Services\AdminDashboard\ManajemenData\KinerjaEkonomiManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportKinerjaEkonomiCsvJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        private readonly string $batchId,
        private readonly string $storagePath,
        private readonly array $columnMapping = []
    ) {
        $this->onQueue('imports');
    }

    public function handle(KinerjaEkonomiManagementService $service): void
    {
        $path = Storage::disk('local')->path($this->storagePath);
        $service->importFileIntoBatch($this->batchId, $path, $this->columnMapping);
        Storage::disk('local')->delete($this->storagePath);
    }

    public function failed(Throwable $exception): void
    {
        app(KinerjaEkonomiManagementService::class)->markBatchFailed(
            $this->batchId,
            $exception->getMessage()
        );
    }
}
