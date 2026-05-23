<?php

namespace App\Services\AdminDashboard;

use App\Models\Contact;
use App\Repositories\AdminDashboard\ContactManagement\ContactManagementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactManagementService
{
    public function __construct(
        private readonly ContactManagementRepositoryInterface $repository
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

    public function findByIdentifier(string $identifier): Contact
    {
        return $this->repository->findByIdentifier($identifier);
    }

    public function update(string $identifier, array $data): Contact
    {
        $contact = $this->repository->findByIdentifier($identifier);

        return $this->repository->update($contact, $data);
    }

    public function delete(string $identifier): Contact
    {
        $contact = $this->repository->findByIdentifier($identifier);
        $snapshot = clone $contact;
        $this->repository->delete($contact);

        return $snapshot;
    }
}
