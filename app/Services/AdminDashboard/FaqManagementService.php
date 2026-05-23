<?php

namespace App\Services\AdminDashboard;

use App\Models\FaqTopic;
use App\Repositories\AdminDashboard\FaqManagement\FaqManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FaqManagementService
{
    public function __construct(
        private readonly FaqManagementRepositoryInterface $repository
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function getSummary(): array
    {
        return $this->repository->getSummary();
    }

    public function findByIdentifier(string $identifier): FaqTopic
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function create(array $data): FaqTopic
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): FaqTopic
    {
        $topic = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($topic, $data);
    }

    public function delete(string $identifier): FaqTopic
    {
        $topic = $this->repository->findByIdentifier($identifier);
        $snapshot = clone $topic;
        $this->repository->delete($topic);

        return $snapshot;
    }
}
