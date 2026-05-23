<?php

namespace App\Repositories\AdminDashboard\ContactManagement;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ContactManagementRepository implements ContactManagementRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('nama', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('jenis', 'like', '%' . $search . '%')
                        ->orWhere('pesan', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis', $jenis))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getSummary(): array
    {
        $latestContact = Contact::query()
            ->latest('created_at')
            ->latest('id')
            ->first();

        return [
            'total_contact' => Contact::query()->count(),
            'jenis_aktif' => Contact::query()->distinct('jenis')->count('jenis'),
            'contact_terbaru' => $latestContact ? $this->transformContact($latestContact) : null,
        ];
    }

    public function findByIdentifier(string $identifier): Contact
    {
        $contact = $this->baseQuery()
            ->where('uuid', $identifier)
            ->first();

        if (!$contact) {
            throw (new ModelNotFoundException())->setModel(Contact::class, [$identifier]);
        }

        return $contact;
    }

    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data) {
            $contact->fill([
                'nama' => $data['nama'],
                'email' => $data['email'],
                'jenis' => $data['jenis'],
                'pesan' => $data['pesan'],
            ])->save();

            return $this->findByIdentifier($contact->uuid);
        });
    }

    public function delete(Contact $contact): void
    {
        DB::transaction(function () use ($contact) {
            $contact->delete();
        });
    }

    private function baseQuery(): Builder
    {
        return Contact::query();
    }

    private function transformContact(Contact $contact): array
    {
        return [
            'id' => $contact->uuid,
            'nama' => $contact->nama,
            'email' => $contact->email,
            'jenis' => $contact->jenis,
            'pesan' => $contact->pesan,
            'created_at' => $contact->created_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
            'updated_at' => $contact->updated_at?->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
