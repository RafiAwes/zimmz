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
        'admin_status' => 'new',
        'user_status' => 'pending',
        'runner_status' => null,
        'runner_id' => null,
    ]);

    $runnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/{$runnerUser->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.admin_status', 'pending')
        ->assertJsonPath('data.user_status', 'pending')
        ->assertJsonPath('data.runner_status', 'new')
        ->assertJsonPath('data.runner_id', $runnerUser->id);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'admin_status' => 'pending',
        'user_status' => 'pending',
        'runner_status' => 'new',
        'runner_id' => $runnerUser->id,
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

    $order = Order::factory()->create(['admin_status' => 'completed']);

    $runnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create(['user_id' => $runnerUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/accept-and-assign/{$order->id}/{$runnerUser->id}");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order_id']);
});

test('admin can request delivery confirmation after runner completes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $orderOwner = User::factory()->create(['role' => 'user']);
    $runnerUser = User::factory()->create(['role' => 'runner']);

    $order = Order::factory()->create([
        'user_id' => $orderOwner->id,
        'runner_id' => $runnerUser->id,
        'admin_status' => 'pending',
        'user_status' => 'pending',
        'runner_status' => 'completed',
        'delivery_requested' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/request-delivery/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.user_status', 'pending_approval');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'user_status' => 'pending_approval',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $orderOwner->id,
        'type' => 'delivery_confirmation_request',
        'related_id' => $order->id,
    ]);
});

test('admin request delivery fails if order is not completed by runner', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $order = Order::factory()->create([
        'admin_status' => 'pending',
        'user_status' => 'ongoing',
        'runner_status' => 'ongoing',
        'delivery_requested' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/admin/request-delivery/{$order->id}");

    $response->assertStatus(422);
});
