<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('products', 'name')->ignore($this->product->id)
            ],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'amount'      => ['sometimes', 'integer', 'min:0'],
            'photo'       => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
        ];
    }
}
