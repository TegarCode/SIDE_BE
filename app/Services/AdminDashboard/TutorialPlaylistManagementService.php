<?php

namespace App\Services\AdminDashboard;

use App\Models\TutorialPlaylist;
use App\Repositories\AdminDashboard\TutorialPlaylistManagement\TutorialPlaylistManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TutorialPlaylistManagementService
{
    public function __construct(
        private readonly TutorialPlaylistManagementRepositoryInterface $repository
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

    public function findByIdentifier(string $identifier): TutorialPlaylist
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function create(array $data): TutorialPlaylist
    {
        return $this->repository->create($data);
    }

    public function update(string $identifier, array $data): TutorialPlaylist
    {
        $playlist = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($playlist, $data);
    }

    public function delete(string $identifier): TutorialPlaylist
    {
        $playlist = $this->repository->findByIdentifier($identifier);
        $snapshot = clone $playlist;
        $this->repository->delete($playlist);

        return $snapshot;
    }
}
