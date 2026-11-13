<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'id' => ['nullable', 'uuid'],
            'category_id' => [
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
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori tidak valid atau sudah dihapus.',
            'pocket_id.exists' => 'Pocket tidak valid atau sudah dihapus.',
            'type.required' => 'Tipe transaksi wajib diisi.',
            'type.in' => 'Tipe transaksi harus income atau expense.',
            'amount.required' => 'Jumlah transaksi wajib diisi.',
            'amount.min' => 'Jumlah transaksi minimal Rp 0.01.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'transaction_date.date' => 'Format tanggal tidak valid.',
        ];
    }
}
