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

test('runner can decline order and clear assignment', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    $runner = Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
        'runner_status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/decline-order/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.runner_id', null)
        ->assertJsonPath('data.runner_status', null);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'new',
        'runner_id' => null,
        'runner_status' => null,
    ]);
});

test('runner can accept assigned order and sees ongoing status', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    $runner = Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
        'runner_status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/accept-order/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'ongoing')
        ->assertJsonPath('data.runner_status', 'assigned');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
        'runner_status' => 'assigned',
    ]);
});

test('runner can complete ongoing order and user gets completion confirmation notification', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);

    $runner = Runner::factory()->create(['user_id' => $runnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
        'runner_status' => 'assigned',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/runner/order-completed/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.runner_status', 'delivered');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'completed',
        'runner_status' => 'delivered',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $orderOwner->id,
        'type' => 'order_completion_confirmation',
        'related_id' => $order->id,
    ]);
});

test('runner cannot accept order assigned to another runner', function () {
    $runnerUser = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runnerUser);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $otherRunnerUser = User::factory()->create(['role' => 'runner']);
    $otherRunner = Runner::factory()->create(['user_id' => $otherRunnerUser->id]);
    $orderOwner = User::factory()->create(['role' => 'user']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'status' => 'pending',
        'runner_id' => $otherRunner->id,
        'runner_status' => 'pending',
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
