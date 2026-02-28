<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a user can send a message', function () {
    Event::fake();
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $response = $this->actingAs($sender, 'api')
        ->postJson('/api/messages/send', [
            'receiver_id' => $receiver->id,
            'message' => 'Hello there!',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.message', 'Hello there!')
        ->assertJsonPath('data.sender_id', $sender->id)
        ->assertJsonPath('data.receiver_id', $receiver->id);

    $this->assertDatabaseHas('messages', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'message' => 'Hello there!',
    ]);

    Event::assertDispatched(MessageSent::class, function ($event) use ($receiver) {
        return $event->message->receiver_id === $receiver->id;
    });
});

test('a user can retrieve message history', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    Message::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'message' => 'Message 1',
    ]);

    Message::create([
        'sender_id' => $receiver->id,
        'receiver_id' => $sender->id,
        'message' => 'Message 2',
    ]);

    $response = $this->actingAs($sender, 'api')
        ->getJson("/api/messages/get-messages/{$receiver->id}");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Message 1')
        ->assertJsonPath('data.1.message', 'Message 2');
});

test('unauthorized users cannot send or retrieve messages', function () {
    $response = $this->postJson('/api/messages/send', [
        'receiver_id' => 1,
        'message' => 'Hello',
    ]);

    $response->assertStatus(401);

    $response = $this->getJson('/api/messages/get-messages/1');

    $response->assertStatus(401);
});
