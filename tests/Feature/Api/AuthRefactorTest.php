<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('user can register and verify email with unified verify-otp api', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'user',
    ]);

    $response->assertStatus(201);

    $user = User::where('email', 'test@example.com')->first();
    expect($user->email_verified_at)->toBeNull();

    // Manual OTP injection for testing
    $otp = '123456';
    $user->update([
        'otp' => Hash::make($otp),
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    $verifyResponse = $this->postJson('/api/auth/verify-otp', [
        'email' => 'test@example.com',
        'otp' => $otp,
    ]);

    $verifyResponse->assertStatus(200);
    $verifyResponse->assertJsonStructure(['data' => ['access_token', 'user']]);

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
});

test('user can reset password using otp and then authenticated reset-password api', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('old_password'),
        'email_verified_at' => now(),
    ]);

    // Step 1: Forgot password
    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(200);

    $user->refresh();
    // Simulate setting a known OTP on the user model (consistent with new logic)
    $otp = '654321';
    $user->update([
        'otp' => Hash::make($otp),
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    // Step 2: Verify OTP to get reset token (JWT)
    $verifyResponse = $this->postJson('/api/auth/verify-otp', [
        'email' => 'reset@example.com',
        'otp' => $otp,
    ]);

    $verifyResponse->assertStatus(200);
    $verifyResponse->assertJsonStructure(['data' => ['access_token']]);
    $token = $verifyResponse->json('data.access_token');
    expect($token)->not->toBeNull();

    // Step 3: Reset password with token in header and email in body
    $resetResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'password' => 'new_password',
            'password_confirmation' => 'new_password',
        ]);

    $resetResponse->assertStatus(200);

    $user->refresh();
    expect(Hash::check('new_password', $user->password))->toBeTrue();

    // Verify token record is deleted
    $tokenExists = DB::table('password_reset_tokens')->where('email', 'reset@example.com')->exists();
    expect($tokenExists)->toBeFalse();
});
