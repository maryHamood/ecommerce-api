<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}


    public function register(RegisterRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');

        if (User::where('phone', $phone)->exists()) {
            return ApiResponse::error('This phone is already registered. Please login.', ['phone' => ['Already registered.']], 422);
        }

        $payload = [
            'name'     => $request->validated('name'),
            'phone'    => $phone,
            'password' => Hash::make($request->validated('password')),
            'iat'      => time(),
            'exp'      => time() + 300,
        ];


        $registrationToken = JWTAuth::getJWTProvider()->encode($payload);


        $data = $this->otpService->send($phone);


        $data['registration_token'] = $registrationToken;

        return ApiResponse::success(
            $data,
            'OTP sent. Use the registration_token with your OTP to verify.',
            201
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $token = $request->validated('registration_token');


        $this->otpService->verify($phone, $request->validated('code'));

        try {

            $payload = JWTAuth::getJWTProvider()->decode($token);


            if ($payload['phone'] !== $phone) {
                throw new \Exception('Token phone does not match.');
            }


            $user = User::create([
                'name'     => $payload['name'],
                'phone'    => $payload['phone'],
                'password' => $payload['password'],
                'role'     => 'customer',
            ]);


            $authToken = JWTAuth::fromUser($user);

            return ApiResponse::success(
                $this->authPayload($user, $authToken),
                'Account created and verified successfully.',
                201
            );

        } catch (\Throwable $e) {

            return ApiResponse::error(
                'Invalid or expired registration token. Please register again.',
                null,
                401
            );
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $phone    = $request->validated('phone');
        $password = $request->validated('password');

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return ApiResponse::error(
                'Account does not exist. Please register first.',
                ['phone' => ['No account found with this phone.']],
                404
            );
        }

        if (! Hash::check($password, $user->password)) {
            return ApiResponse::error(
                'Invalid credentials.',
                ['password' => ['The password is incorrect.']],
                401
            );
        }

        $token = JWTAuth::fromUser($user);

        return ApiResponse::success(
            $this->authPayload($user, $token),
            'Logged in successfully.'
        );
    }


    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');

        if (User::where('phone', $phone)->exists()) {
            return ApiResponse::error(
                'Account already exists. Please login.',
                ['phone' => ['Account already exists.']],
                422
            );
        }

        $data = $this->otpService->resend($phone);

        return ApiResponse::success($data, 'OTP resent successfully.');
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(auth('api')->user());
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return ApiResponse::success(null, 'Logged out successfully.');
    }

    private function authPayload(User $user, string $token): array
    {
        return [
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ];
    }
}
