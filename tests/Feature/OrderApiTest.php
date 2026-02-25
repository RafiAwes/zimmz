<?php

use App\Models\Ferry;
use App\Models\Island;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($this->user);
    $this->withHeader('Authorization', 'Bearer ' . $this->token);
});

test('can list all statuses when status is null', function () {
    Order::factory()->create(['status' => 'new']);
    Order::factory()->create(['status' => 'pending']);

    $response = $this->getJson('/api/order/get-all');

    $response->assertStatus(200)
        ->assertJsonFragment(['new', 'pending']);
});

test('can list orders filtered by status', function () {
    Order::factory()->count(2)->create(['status' => 'new', 'user_id' => $this->user->id]);
    Order::factory()->create(['status' => 'pending', 'user_id' => $this->user->id]);

    $response = $this->getJson('/api/order/get-all?status=new');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data.data');
});

test('can create food delivery order', function () {
    $restaurant = Restaurant::factory()->create();

    $payload = [
        'name' => 'John Doe',
        'details' => 'Extra spicy',
        'time' => '12:00 PM',
        'total_cost' => 50.00,
        'drop_location' => '123 Beach Rd',
        'type' => 'food_delivery',
        'restaurant_id' => $restaurant->id,
        'food_cost' => 40.00,
        'delivery_fee' => 5.00,
        'service_fee' => 5.00,
    ];

    $response = $this->postJson('/api/order/create', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'food_delivery');

    $this->assertDatabaseHas('orders', ['name' => 'John Doe']);
    $this->assertDatabaseHas('food_deliveries', ['restaurant_id' => $restaurant->id]);
});

test('can create ferry drop order', function () {
    $ferry = Ferry::factory()->create();
    $island = Island::factory()->create();

    $payload = [
        'name' => 'Island Package',
        'total_cost' => 30.00,
        'drop_location' => 'Main Pier',
        'type' => 'ferry_drops',
        'pickup_location' => 'Downtown Office',
        'ferry_id' => $ferry->id,
        'island_id' => $island->id,
        'drop_fee' => 20.00,
        'package_fee' => 10.00,
    ];

    $response = $this->postJson('/api/order/create', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'ferry_drops');

    $this->assertDatabaseHas('orders', ['name' => 'Island Package']);
    $this->assertDatabaseHas('ferry_drops', ['ferry_id' => $ferry->id]);
});

test('can update an order', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id, 'type' => 'food_delivery']);
    $order->foodDelivery()->create([
        'restaurant_id' => Restaurant::factory()->create()->id,
        'food_cost' => 10,
        'delivery_fee' => 2,
        'service_fee' => 1,
    ]);

    $response = $this->putJson("/api/order/update/{$order->id}", [
        'name' => 'Updated Name',
        'status' => 'pending',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
});

test('can delete an order', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->deleteJson("/api/order/delete/{$order->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

test('can show order details', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->getJson("/api/order/details/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id);
});
