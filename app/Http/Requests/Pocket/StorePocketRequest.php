<?php

namespace App\Http\Requests\Pocket;

use Illuminate\Foundation\Http\FormRequest;

class StorePocketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'has_auto_target' => ['nullable', 'boolean'],
            'target_months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pocket wajib diisi.',
            'name.max' => 'Nama pocket maksimal 50 karakter.',
            'color.regex' => 'Format warna harus hex (#RRGGBB).',
            'target_amount.min' => 'Target tidak boleh negatif.',
            'target_months.min' => 'Target bulan minimal 1.',
            'target_months.max' => 'Target bulan maksimal 24.',
        ];
    }
}
