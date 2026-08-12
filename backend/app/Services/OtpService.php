<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class OtpService
{
    public function createFor(User $user): string
    {
        OtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public function verify(User $user, string $code): void
    {
        $otp = OtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast() || ! Hash::check($code, $otp->code_hash)) {
            throw new RuntimeException('Code OTP invalide ou expire.');
        }

        $otp->forceFill(['used_at' => now()])->save();
    }
}
