<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    use NormalizesPhone;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be in international format, e.g. +963912345678.',
        ];
    }
}
