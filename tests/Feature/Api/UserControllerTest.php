<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('authenticated user can view user details', function () {
    $authUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($authUser);

    $targetUser = User::factory()->create(['role' => 'user']);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson("/api/user/details/{$targetUser->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $targetUser->id)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'total_orders',
            ]
        ]);
});

test('unauthenticated user cannot view user details', function () {
    $targetUser = User::factory()->create(['role' => 'user']);
    $response = $this->getJson("/api/user/details/{$targetUser->id}");

    $response->assertStatus(401);
});
