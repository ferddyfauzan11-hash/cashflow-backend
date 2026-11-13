<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['sometimes', 'required', Rule::in(['lend', 'borrow'])],
            'principal_amount' => ['sometimes', 'required', 'numeric', 'min:1'],
            'remaining_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'completed', 'overdue'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.max' => 'Nama kontak maksimal 100 karakter.',
            'type.in' => 'Tipe pinjaman harus lend atau borrow.',
            'principal_amount.min' => 'Jumlah pinjaman minimal Rp 1.',
            'remaining_amount.min' => 'Sisa pinjaman tidak boleh negatif.',
            'status.in' => 'Status pinjaman tidak valid.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
        ];
    }
}
