<?php

namespace App\Repositories\AdminDashboard\ContactManagement;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function getSummary(): array;

    public function findByIdentifier(string $identifier): Contact;

    public function update(Contact $contact, array $data): Contact;

    public function delete(Contact $contact): void;
}
