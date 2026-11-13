<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['lend', 'borrow'])],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Nama kontak wajib diisi.',
            'contact_name.max' => 'Nama kontak maksimal 100 karakter.',
            'type.required' => 'Tipe pinjaman wajib diisi.',
            'type.in' => 'Tipe pinjaman harus lend atau borrow.',
            'principal_amount.required' => 'Jumlah pinjaman wajib diisi.',
            'principal_amount.min' => 'Jumlah pinjaman minimal Rp 1.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
        ];
    }
}
