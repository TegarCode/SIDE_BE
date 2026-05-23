<?php

namespace App\Repositories\AdminDashboard\FaqManagement;

use App\Models\FaqTopic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FaqManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): FaqTopic;

    public function create(array $data): FaqTopic;

    public function update(FaqTopic $topic, array $data): FaqTopic;

    public function delete(FaqTopic $topic): void;
}
