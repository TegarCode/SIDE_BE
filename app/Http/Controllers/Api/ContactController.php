<?php
// app/Http/Controllers/ContactController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function store(ContactRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();

            $ip = $request->ip() ?? null;
            $ipHash = $ip ? hash('sha256', $ip) : null;
            $userAgent = substr($request->userAgent() ?? '', 0, 255);

            $contact = Contact::create([
                'uuid' => (string) Str::uuid(),
                'nama' => $payload['nama'],
                'email' => $payload['email'],
                'jenis' => $payload['jenis'],
                'pesan' => $payload['pesan'],
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
            ]);

            // optional: dispatch email / notification job here

            return response()->json([
                'status' => true,
                'message' => 'Pesan berhasil dikirim.',
                'data' => ['id' => $contact->id],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Contact store error', ['e' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }
}
