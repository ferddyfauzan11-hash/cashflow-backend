<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'category_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)->where('is_deleted', false);
                }),
            ],
            'pocket_id' => [
                'nullable',
                'uuid',
                Rule::exists('pockets', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)->where('is_deleted', false);
                }),
            ],
            'type' => ['sometimes', 'required', Rule::in(['income', 'expense'])],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['sometimes', 'required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori tidak valid atau sudah dihapus.',
            'pocket_id.exists' => 'Pocket tidak valid atau sudah dihapus.',
            'type.in' => 'Tipe transaksi harus income atau expense.',
            'amount.min' => 'Jumlah transaksi minimal Rp 0.01.',
            'transaction_date.date' => 'Format tanggal tidak valid.',
        ];
    }
}
