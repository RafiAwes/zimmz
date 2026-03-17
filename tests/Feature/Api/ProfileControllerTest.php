<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('authenticated user can view their profile with order counts', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    // Act
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/profile');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'total_orders',
                'total_food_delivery_orders',
                'total_ferry_drop_orders',
                'total_tasks_created',
                'runner_orders_completed',
                'runner_orders_pending',
            ],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.total_orders', 0);
});

test('unauthenticated user cannot view profile', function () {
    $response = $this->getJson('/api/profile');

    $response->assertStatus(401);
});

test('authenticated runner can view their profile with runner stats', function () {
    $user = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($user);

    // Act
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/profile');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'runner_stats' => [
                    'total_orders',
                    'completed_orders',
                    'pending_orders',
                    'total_tasks',
                    'completed_tasks',
                    'pending_tasks',
                ],
            ],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.runner_stats.total_orders', 0);
});
