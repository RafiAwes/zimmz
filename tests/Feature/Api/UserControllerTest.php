<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('authenticated user can view user details', function () {
    $authUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($authUser);

    $targetUser = User::factory()->create(['role' => 'user']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
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
            ],
        ]);
});

test('unauthenticated user cannot view user details', function () {
    $targetUser = User::factory()->create(['role' => 'user']);
    $response = $this->getJson("/api/user/details/{$targetUser->id}");

    $response->assertStatus(401);
});

test('admin can view users list where role is user with search and pagination', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $matchedUser = User::factory()->create([
        'role' => 'user',
        'name' => 'Search Target User',
    ]);

    User::factory()->create([
        'role' => 'user',
        'name' => 'Another Regular User',
    ]);

    User::factory()->create([
        'role' => 'runner',
        'name' => 'Search Runner User',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user/list?search=Search&per_page=1');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.per_page', 1)
        ->assertJsonPath('data.data.0.id', $matchedUser->id)
        ->assertJsonPath('data.data.0.role', 'user');
});

test('non admin cannot view users list', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user/list');

    $response->assertStatus(403);
});

test('unauthenticated user cannot view users list', function () {
    $response = $this->getJson('/api/user/list');

    $response->assertStatus(401);
});

test('admin can view lost users list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $bannedUser = User::factory()->create([
        'role' => 'user',
        'ban_type' => 'week',
        'ban_expires_at' => now()->addWeek(),
    ]);

    $inactiveUser = User::factory()->create([
        'role' => 'user',
        'is_active' => false,
    ]);

    $activeUser = User::factory()->create([
        'role' => 'user',
        'is_active' => true,
    ]);

    $runnerUser = User::factory()->create([
        'role' => 'runner',
        'is_active' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user/lost-users?per_page=10');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 2);

    $returnedIds = collect($response->json('data.data'))->pluck('id');

    expect($returnedIds)->toContain($bannedUser->id);
    expect($returnedIds)->toContain($inactiveUser->id);
    expect($returnedIds)->not->toContain($activeUser->id);
    expect($returnedIds)->not->toContain($runnerUser->id);
});

test('non admin cannot view lost users list', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user/lost-users');

    $response->assertStatus(403);
});

test('unauthenticated user cannot view lost users list', function () {
    $response = $this->getJson('/api/user/lost-users');

    $response->assertStatus(401);
});
