<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    // ─── حالات إرسال UltraMsg ───
    private const SEND_SUCCESS   = 'success';   // تأكد التسليم
    private const SEND_REJECTED  = 'rejected';
    private const SEND_UNCERTAIN = 'uncertain';

    public function send(string $phone): array
    {
        // ... (نفس كود منع التكرار 60 ثانية)

        $code = $this->generateCode();

        $otp = Otp::create([
            'phone'      => $phone,
            'code'       => $code,
            'expires_at' => now()->addSeconds(config('ultramsg.otp_ttl')),
        ]);

        $result = $this->sendViaWhatsApp($phone, $code);

        if ($result === self::SEND_REJECTED) {
            $otp->delete();
            throw ValidationException::withMessages([
                'phone' => ['Failed to send OTP. Please try again later.'],
            ]);
        }

        return ['phone' => $phone, 'expires_in' => config('ultramsg.otp_ttl')];
    }

    public function resend(string $phone): array
    {
        $existing = Otp::where('phone', $phone)->first();

        if (! $existing) {
            throw ValidationException::withMessages([
                'phone' => ['No pending registration. Please register first.'],
            ]);
        }

        if ($existing->updated_at->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'phone' => ['Please wait 60 seconds before requesting a new OTP.'],
            ]);
        }

        $previousCode = $existing->code;
        $newCode      = $this->generateCode();

        $existing->update([
            'code'       => $newCode,
            'expires_at' => now()->addSeconds(config('ultramsg.otp_ttl')),
        ]);

        $result = $this->sendViaWhatsApp($phone, $newCode);

        // رفض صريح → أعد الكود القديم (المستخدم قد يكون لديه)
        if ($result === self::SEND_REJECTED) {
            $existing->update(['code' => $previousCode]);
            throw ValidationException::withMessages([
                'phone' => ['Failed to send OTP. Please try again later.'],
            ]);
        }

        return ['phone' => $phone, 'expires_in' => config('ultramsg.otp_ttl')];
    }

    public function verify(string $phone, string $code): void
    {
        DB::transaction(function () use ($phone, $code) {
            $otp = Otp::where('phone', $phone)->lockForUpdate()->first();

            if (! $otp) {
                throw ValidationException::withMessages(['phone' => ['No pending registration.']]);
            }

            if (! hash_equals($otp->code, $code)) {
                throw ValidationException::withMessages(['code' => ['Invalid OTP.']]);
            }

            if ($otp->expires_at->isPast()) {
                $otp->delete();
                throw ValidationException::withMessages(['code' => ['OTP has expired.']]);
            }

            // حذف الـ OTP بعد النجاح
            $otp->delete();
        });
    }

    private function generateCode(): string
    {
        $length = (int) config('ultramsg.otp_length', 6);
        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );
    }

    private function sendViaWhatsApp(string $phone, string $code): string
    {
        $instance = config('ultramsg.instance_id');
        $token    = config('ultramsg.token');
        $url      = config('ultramsg.base_url') . "/{$instance}/messages/chat?token=" . urlencode($token);

        $to      = ltrim($phone, '+');
        $minutes = config('ultramsg.otp_ttl') / 60;
        $message = "Your OTP code is: {$code}\nValid for {$minutes} minutes.";

        Log::info('UltraMsg REQUEST', [
            'url'          => $url,
            'to'           => $to,
            'token_prefix' => substr($token ?? '', 0, 8) . '...',
        ]);

        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(20)->connectTimeout(10)->asForm()->post($url, [
                    'to'       => $to,
                    'body'     => $message,
                    'priority' => 0,
                ]);

                Log::info('UltraMsg RESPONSE', [
                    'attempt' => $attempt,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);


                if (! $response->successful()) {
                    return self::SEND_REJECTED;
                }

                $sent = $response->json('sent');


                if (! in_array($sent, ['true', true, 1, '1'], true)) {
                    return self::SEND_REJECTED;
                }

                return self::SEND_SUCCESS;

            } catch (\Throwable $e) {
                Log::warning('UltraMsg ATTEMPT FAILED', [
                    'attempt' => $attempt,
                    'class'   => get_class($e),
                    'message' => $e->getMessage(),
                ]);


                if ($attempt < $maxAttempts) {
                    usleep(500_000);
                    continue;
                }


                return self::SEND_UNCERTAIN;
            }
        }

        return self::SEND_UNCERTAIN;
    }
}
