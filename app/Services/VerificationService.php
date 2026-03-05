<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SendOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationService
{
    protected const EXPIRATION_TIME = 300; // 5 minutes

    private function generateOtp(): string
    {
        $otp = random_int(100000, 999999);

        return $otp;
    }

    public function sendOtp(User $user): array
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addSeconds(self::EXPIRATION_TIME),
        ]);

        $user->notify(new SendOtpMail($generate));

        return ['success' => true, 'message' => 'OTP sent successfully.'];
    }

    public function sendForgotPasswordOtp(User $user): array
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        $user->notify(new SendOtpMail($generate));

        return ['success' => true, 'message' => 'Password reset OTP sent successfully.'];
    }

    public function verifyOtp(User $user, string $otp): array
    {
        if ($user->email_verified_at === null) {
            // Registration/Email Verification flow
            if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
                return [
                    'success' => false,
                    'message' => 'OTP has expired.',
                ];
            }

            if ($user->otp === null) {
                return [
                    'success' => false,
                    'message' => 'No OTP found for this user.',
                ];
            }

            if (Hash::check($otp, $user->otp)) {
                $user->update([
                    'otp' => null,
                    'otp_expires_at' => null,
                    'otp_verified' => true,
                    'otp_verified_at' => now(),
                    'email_verified_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Email verified successfully.',
                ];
            }
        } else {
            // Forgot Password flow
            $resetRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

            if (! $resetRecord) {
                return [
                    'success' => false,
                    'message' => 'No password reset OTP found for this user.',
                ];
            }

            if (now()->diffInSeconds($resetRecord->created_at) > self::EXPIRATION_TIME) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();

                return [
                    'success' => false,
                    'message' => 'OTP has expired.',
                ];
            }

            if (Hash::check($otp, $resetRecord->token)) {
                $user->update([
                    'otp_verified' => true,
                    'otp_verified_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'OTP verified successfully. You can now reset your password.',
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Invalid OTP.',
        ];
    }

    public function resendOtp(User $user): array
    {
        return $this->sendOtp($user);
    }

    public function generatePasswordResetToken(string $email): string
    {
        $token = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        return $token;
    }

    public function verifyPasswordResetToken(string $email, string $token): bool
    {
        // For password reset, we check the token or the otp_verified flag
        // However, the user said "it will use password reset token"
        // But their snippet says "You can now reset your password" after OTP verify
        // Let's implement BOTH: OTP verify returns a token, and resetPassword uses it.
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record) {
            return false;
        }

        if (now()->parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return false;
        }

        if (Hash::check($token, $record->token)) {
            return true;
        }

        return false;
    }

    public function deletePasswordResetToken(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
