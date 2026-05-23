<?php

// app/Http/Requests/ContactRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'jenis' => ['required', 'in:PERTANYAAN,MASUKAN,SARAN'],
            'pesan' => ['required', 'string', 'min:6', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama harus diisi.',
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Format email tidak valid.',
            'jenis.in' => 'Jenis pesan tidak valid.',
            'pesan.required' => 'Pesan tidak boleh kosong.',
            'pesan.min' => 'Pesan terlalu singkat.',
        ];
    }
}
