<?php

namespace App\Http\Controllers\Api\AdminDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboard\ContactManagementRequest;
use App\Models\Contact;
use App\Services\AdminDashboard\ContactManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class ContactManagementController extends Controller
{
    public function __construct(
        private readonly ContactManagementService $service
    ) {
    }

    public function index(ContactManagementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $contacts = $this->service->paginate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contacts fetched successfully',
            'data' => [
                'summary' => $this->service->getSummary(),
                'items' => collect($contacts->items())
                    ->map(fn (Contact $contact) => $this->transformContact($contact))
                    ->values(),
                'meta' => [
                    'page' => $contacts->currentPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                    'last_page' => $contacts->lastPage(),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $contact = $this->service->findByIdentifier($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact fetched successfully',
            'data' => $this->transformContact($contact),
        ]);
    }

    public function update(ContactManagementRequest $request, string $id): JsonResponse
    {
        try {
            $contact = $this->service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $this->transformContact($contact),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $contact = $this->service->delete($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
            'data' => [
                'id' => $contact->uuid,
            ],
        ]);
    }

    private function transformContact(Contact $contact): array
    {
        return [
            'id' => $contact->uuid,
            'nama' => $contact->nama,
            'email' => $contact->email,
            'jenis' => $contact->jenis,
            'pesan' => $contact->pesan,
            'created_at' => $this->formatDate($contact->created_at),
            'updated_at' => $this->formatDate($contact->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date?->utc()->format('Y-m-d\\TH:i:s\\Z');
    }
}
