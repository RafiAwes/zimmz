<?php

use App\Models\Order;
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
        ->assertJsonPath('data.runner.category', 'food_delivery')
        ->assertJsonStructure([
            'data' => [
                'runner_stats' => [
                    'total_orders',
                    'completed_orders',
                    'pending_orders',
                    'total_tasks',
                    'completed_tasks',
                    'pending_tasks',
                ],
            ],
        ]);
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
        ->assertJsonPath('data.data.0.runner.type', 'assigned')
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'runner_stats' => [
                            'total_orders',
                            'completed_orders',
                            'pending_orders',
                            'total_tasks',
                            'completed_tasks',
                            'pending_tasks',
                        ],
                    ],
                ],
            ],
        ]);
});

test('unauthenticated user cannot view runners list', function () {
    $response = $this->getJson('/api/runner/list');

    $response->assertStatus(401);
});

test('runner can decline order and clear assignment', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'admin_status' => 'pending',
        'user_status' => 'pending',
        'runner_status' => 'new',
        'runner_id' => $runnerUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/decline-order/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', null)
        ->assertJsonPath('data.runner_id', null);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'admin_status' => 'new',
        'user_status' => 'pending',
        'runner_status' => null,
        'runner_id' => null,
    ]);
});

test('runner can accept assigned order and sees ongoing status', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'admin_status' => 'pending',
        'user_status' => 'pending',
        'runner_status' => 'new',
        'runner_id' => $runnerUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/accept-order/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'ongoing');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'admin_status' => 'pending',
        'user_status' => 'ongoing',
        'runner_status' => 'ongoing',
        'runner_id' => $runnerUser->id,
    ]);
});

test('runner can complete ongoing order and admin gets notification', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'admin_status' => 'pending',
        'user_status' => 'ongoing',
        'runner_status' => 'ongoing',
        'runner_id' => $runnerUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/order-completed/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'runner_status' => 'completed',
        'user_status' => 'pending',
        'delivery_requested' => true,
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $admin->id,
        'type' => 'order_completed',
        'related_id' => $order->id,
    ]);
});

test('runner cannot accept order assigned to another runner', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $otherRunnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create(['user_id' => $otherRunnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'admin_status' => 'pending',
        'user_status' => 'pending',
        'runner_status' => 'new',
        'runner_id' => $otherRunnerUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/accept-order/{$order->id}");

    $response->assertStatus(403);
});

test('unauthenticated user cannot decline order', function () {
    $order = Order::factory()->create();

    $response = $this->postJson("/api/runner/decline-order/{$order->id}");

    $response->assertStatus(401);
});

test('non runner cannot decline order', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);
    $order = Order::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/decline-order/{$order->id}");

    $response->assertStatus(403);
});
