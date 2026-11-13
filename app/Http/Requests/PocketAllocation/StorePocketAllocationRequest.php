<?php

namespace App\Http\Requests\PocketAllocation;

use Illuminate\Foundation\Http\FormRequest;

class StorePocketAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_pocket_id' => ['nullable', 'uuid'],
            'to_pocket_id' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'allocation_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah transfer wajib diisi.',
            'amount.min' => 'Jumlah transfer minimal Rp 1.',
            'allocation_date.required' => 'Tanggal transfer wajib diisi.',
            'allocation_date.date' => 'Format tanggal tidak valid.',
        ];
    }
}
