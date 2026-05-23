<?php

namespace App\Repositories\AdminDashboard\TutorialPlaylistManagement;

use App\Models\TutorialPlaylist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TutorialPlaylistManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): TutorialPlaylist;

    public function create(array $data): TutorialPlaylist;

    public function update(TutorialPlaylist $playlist, array $data): TutorialPlaylist;

    public function delete(TutorialPlaylist $playlist): void;
}
