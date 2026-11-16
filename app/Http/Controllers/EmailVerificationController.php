<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Helpers\ApiResponse;

class EmailVerificationController extends Controller
{
    /**
     * Add or update the user's email and send verification link.
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Invalid email address.',
                $validator->errors(),
                422
            );
        }

        $email = $validator->validated()['email'];

        if ($user->email !== $email) {
            $user->email = $email;
            $user->email_verified_at = null;
            $user->save();
        }

        return $this->sendVerificationLink($request);
    }

    /**
     * Send or resend the email verification link.
     */
    public function sendVerificationLink(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user->email) {
            return ApiResponse::error(
                'Please add an email address first.',
                null,
                422
            );
        }

        if ($user->email_verified_at) {
            return ApiResponse::success(
                null,
                'Email address is already verified.'
            );
        }

        try {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'user' => $user->id,
                    'hash' => sha1($user->email),
                ]
            );

            Mail::raw(
                "Hello {$user->name},\n\n"
                . "Please verify your email address using the link below:\n\n"
                . $verificationUrl
                . "\n\nThis link expires in 60 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Verify Your Email Address');
                }
            );

            return ApiResponse::success(
                null,
                'Verification link sent successfully.'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'Failed to send verification email. Please try again.',
                null,
                500
            );
        }
    }

    /**
     * Verify the email using the signed URL.
     */
    public function verify(Request $request, User $user): JsonResponse
    {
        if (!hash_equals(
            sha1($user->email ?? ''),
            (string) $request->route('hash')
        )) {
            return ApiResponse::error(
                'Invalid email verification link.',
                null,
                403
            );
        }

        if ($user->email_verified_at) {
            return ApiResponse::success(
                null,
                'Email address is already verified.'
            );
        }

        $user->email_verified_at = now();
        $user->save();

        return ApiResponse::success(
            null,
            'Email address verified successfully.'
        );
    }
}
