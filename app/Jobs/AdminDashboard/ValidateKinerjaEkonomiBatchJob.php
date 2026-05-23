<?php

namespace App\Jobs\AdminDashboard;

use App\Services\AdminDashboard\ManajemenData\KinerjaEkonomiManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ValidateKinerjaEkonomiBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        private readonly string $batchId,
        private readonly ?int $userId = null
    ) {
        $this->onQueue('imports');
    }

    public function handle(KinerjaEkonomiManagementService $service): void
    {
        $service->validate($this->batchId, $this->userId);
    }

    public function failed(Throwable $exception): void
    {
        app(KinerjaEkonomiManagementService::class)->markBatchFailed(
            $this->batchId,
            $exception->getMessage()
        );
    }
}
