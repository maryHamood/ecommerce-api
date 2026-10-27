<?php

namespace App\Http\Requests\Concerns;

trait NormalizesPhone
{

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone) || $phone === '') {
            return;
        }


        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);


        if (preg_match('/^09\d{8}$/', $phone)) {
            $phone = '+963' . substr($phone, 1);
        }

        elseif (preg_match('/^00963\d{9}$/', $phone)) {
            $phone = '+' . substr($phone, 2);
        }

        elseif (preg_match('/^963\d{9}$/', $phone)) {
            $phone = '+' . $phone;
        }
        $this->merge(['phone' => $phone]);
    }
}
