<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.regex'    => 'Phone must be in international format, e.g. +963912345678.',
            'password.required' => 'Password is required.',
        ];
    }
}
