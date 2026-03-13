<?php

use App\Models\Order;
use App\Models\Runner;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

test('admin can accept an order and assign a runner by runner user id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $orderOwner = User::factory()->create(['role' => 'user']);
    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'status' => 'new',
        'runner_id' => null,
        'runner_status' => null,
    ]);

    $runnerUser = User::factory()->create(['role' => 'runner']);
    $runner = Runner::factory()->create([
        'user_id' => $runnerUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/{$runnerUser->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.runner_id', $runner->id)
        ->assertJsonPath('data.runner_status', 'pending');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
        'runner_status' => 'pending',
    ]);
});

test('non admin cannot accept and assign an order', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $order = Order::factory()->create();
    $runnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/{$runnerUser->id}");

    $response->assertStatus(403);
});

test('accept and assign fails when runner user id is invalid', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $order = Order::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/999999");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['runner_user_id']);
});

test('accept and assign fails for completed order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $order = Order::factory()->create(['status' => 'completed']);

    $runnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/{$runnerUser->id}");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order_id']);
});
