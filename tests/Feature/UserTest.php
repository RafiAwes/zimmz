<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_ban_expires_at_is_casted_to_carbon_instance(): void
    {
        $user = User::factory()->create([
            'ban_expires_at' => now()->addDays(2),
        ]);

        $this->assertInstanceOf(Carbon::class, $user->ban_expires_at);
    }

    public function test_otp_expires_at_is_casted_to_carbon_instance(): void
    {
        $user = User::factory()->create([
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->assertInstanceOf(Carbon::class, $user->otp_expires_at);
    }
}
