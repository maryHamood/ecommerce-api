<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use NormalizesPhone;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'code'  => ['required', 'string', 'size:' . config('ultramsg.otp_length')],
            'registration_token' => ['required', 'string'],
        ];
    }
}
