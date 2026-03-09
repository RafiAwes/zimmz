<?php

use App\Models\Runner;
use App\Models\User;
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
        'type' => 'assigned',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
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

test('authenticated user can view runners list with search pagination and type filter', function () {
    $authUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($authUser);

    $assignedRunner = User::factory()->create([
        'role' => 'runner',
        'name' => 'Ali Assigned Runner',
    ]);
    Runner::create([
        'user_id' => $assignedRunner->id,
        'category' => 'food_delivery',
        'type' => 'assigned',
    ]);

    $registeredRunner = User::factory()->create([
        'role' => 'runner',
        'name' => 'Ali Registered Runner',
    ]);
    Runner::create([
        'user_id' => $registeredRunner->id,
        'category' => 'ferry_drops',
        'type' => 'registered',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/runner/list?search=Ali&type=assigned&per_page=1');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.per_page', 1)
        ->assertJsonPath('data.data.0.id', $assignedRunner->id)
        ->assertJsonPath('data.data.0.runner.type', 'assigned');
});

test('unauthenticated user cannot view runners list', function () {
    $response = $this->getJson('/api/runner/list');

    $response->assertStatus(401);
});
