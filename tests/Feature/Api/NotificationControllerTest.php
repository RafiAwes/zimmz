<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('authenticated user can fetch notifications', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $otherUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'First Notification',
        'message' => 'First message',
        'type' => 'subscription_updated',
        'related_id' => null,
    ]);

    Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'Second Notification',
        'message' => 'Second message',
        'type' => 'subscription_created',
        'related_id' => null,
    ]);

    Notification::query()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other User Notification',
        'message' => 'Should not be visible',
        'type' => 'subscription_deleted',
        'related_id' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/notifications');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.data');
});

test('authenticated user can mark one notification as read', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'Unread Notification',
        'message' => 'Unread message',
        'type' => 'subscription_payment_failed',
        'related_id' => null,
        'is_read' => false,
        'read_at' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson("/api/notifications/mark-as-read/{$notification->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_read', true);

    $notification->refresh();
    expect($notification->is_read)->toBeTrue();
    expect($notification->read_at)->not->toBeNull();
});

test('authenticated user can mark all of their notifications as read', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $otherUser = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $firstNotification = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'User Notification 1',
        'message' => 'Message 1',
        'type' => 'subscription_created',
        'related_id' => null,
        'is_read' => false,
        'read_at' => null,
    ]);

    $secondNotification = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'User Notification 2',
        'message' => 'Message 2',
        'type' => 'subscription_updated',
        'related_id' => null,
        'is_read' => false,
        'read_at' => null,
    ]);

    $otherUserNotification = Notification::query()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other User Notification',
        'message' => 'Other message',
        'type' => 'subscription_deleted',
        'related_id' => null,
        'is_read' => false,
        'read_at' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/notifications/mark-all-as-read');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'All notifications marked as read.');

    $firstNotification->refresh();
    $secondNotification->refresh();
    $otherUserNotification->refresh();

    expect($firstNotification->is_read)->toBeTrue();
    expect($secondNotification->is_read)->toBeTrue();
    expect($otherUserNotification->is_read)->toBeFalse();
});
