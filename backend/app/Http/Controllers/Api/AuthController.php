<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function login(Request $request, OtpService $otpService): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $otp = $otpService->createFor($user);

        return response()->json([
            'message' => 'Code OTP envoye.',
            'user_id' => $user->id,
            'otp_expires_in_minutes' => 10,
            'debug_otp' => app()->isProduction() ? null : $otp,
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otpService, ApiTokenService $tokenService): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);

        try {
            $otpService->verify($user, $data['code']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'code' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => 'Connexion validee.',
            'token' => $tokenService->issue($user),
            'user' => $user->load('agency'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('agency'),
        ]);
    }

    public function logout(Request $request, ApiTokenService $tokenService): JsonResponse
    {
        $tokenService->revokeCurrent($request->bearerToken());

        return response()->json(['message' => 'Deconnexion effectuee.']);
    }
}
