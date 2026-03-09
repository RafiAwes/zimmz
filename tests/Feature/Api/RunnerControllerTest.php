<?php

use App\Models\User;
use App\Models\Runner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('authenticated user can view runner details', function () {
    $authUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($authUser);

    $runnerUser = User::factory()->create(['role' => 'runner']);
    Runner::create([
        'user_id' => $runnerUser->id,
        'category' => 'food_delivery',
        'type' => 'assigned'
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson("/api/runner/details/{$runnerUser->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $runnerUser->id)
        ->assertJsonPath('data.runner.category', 'food_delivery');
});

test('unauthenticated user cannot view runner details', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);

    $response = $this->getJson("/api/runner/details/{$runnerUser->id}");

    $response->assertStatus(401);
});
