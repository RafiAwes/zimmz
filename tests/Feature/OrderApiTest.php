<?php

use App\Models\{Ferry, Island, Order, Restaurant, User};

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($this->user);
    $this->withHeader('Authorization', 'Bearer '.$this->token);
});

test('can list all statuses when status is null', function () {
    Order::factory()->create(['status' => 'new']);
    Order::factory()->create(['status' => 'pending']);

    $response = $this->getJson('/api/order/get-all');

    $response->assertStatus(200);
    $statuses = collect($response->json('data.data'))->pluck('status');
    $this->assertTrue($statuses->contains('new'));
    $this->assertTrue($statuses->contains('pending'));
});

test('can list orders filtered by status', function () {
    Order::factory()->count(2)->create(['status' => 'new', 'user_id' => $this->user->id]);
    Order::factory()->create(['status' => 'pending', 'user_id' => $this->user->id]);

    $response = $this->getJson('/api/order/get-all?status=new');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data.data');
});

test('can list orders filtered by type', function () {
    Order::factory()->create(['type' => 'food_delivery', 'user_id' => $this->user->id]);
    Order::factory()->create(['type' => 'ferry_drops', 'user_id' => $this->user->id]);

    $response = $this->getJson('/api/order/get-all?type=ferry_drop');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.type', 'ferry_drops');
});

test('can list orders filtered by search', function () {
    $matchedOrder = Order::factory()->create([
        'name' => 'Airport pickup order',
        'user_id' => $this->user->id,
    ]);

    Order::factory()->create([
        'name' => 'Groceries run',
        'user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/order/get-all?search=Airport');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $matchedOrder->id);
});

test('can search orders by restaurant and island name', function () {
    $restaurant = Restaurant::factory()->create(['name' => 'Harbor Bites']);
    $island = Island::factory()->create(['name' => 'Palm Island']);
    $ferry = Ferry::factory()->create(['island_id' => $island->id]);

    $foodOrder = Order::factory()->create([
        'type' => 'food_delivery',
        'user_id' => $this->user->id,
    ]);

    $foodOrder->foodDelivery()->create([
        'restaurant_id' => $restaurant->id,
        'food_cost' => 40,
        'delivery_fee' => 5,
        'service_fee' => 5,
    ]);

    $ferryOrder = Order::factory()->create([
        'type' => 'ferry_drops',
        'user_id' => $this->user->id,
    ]);

    $ferryOrder->ferryDrop()->create([
        'pickup_location' => 'Main Pier',
        'ferry_id' => $ferry->id,
        'island_id' => $island->id,
        'drop_fee' => 20,
        'package_fee' => 10,
    ]);

    $restaurantSearchResponse = $this->getJson('/api/order/get-all?search=Harbor');

    $restaurantSearchResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $foodOrder->id)
        ->assertJsonPath('data.data.0.restaurant_name', 'Harbor Bites');

    $islandSearchResponse = $this->getJson('/api/order/get-all?search=Palm');

    $islandSearchResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $ferryOrder->id)
        ->assertJsonPath('data.data.0.island_name', 'Palm Island');
});

test('order list includes restaurant name for food delivery', function () {
    $restaurant = Restaurant::factory()->create(['name' => 'Harbor Bites']);

    $order = Order::factory()->create([
        'type' => 'food_delivery',
        'user_id' => $this->user->id,
    ]);

    $order->foodDelivery()->create([
        'restaurant_id' => $restaurant->id,
        'food_cost' => 40,
        'delivery_fee' => 5,
        'service_fee' => 5,
    ]);

    $response = $this->getJson('/api/order/get-all?type=food_delivery');

    $response->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $order->id)
        ->assertJsonPath('data.data.0.restaurant_name', 'Harbor Bites')
        ->assertJsonPath('data.data.0.island_name', null);
});

test('order list includes island name for ferry drops', function () {
    $island = Island::factory()->create(['name' => 'Palm Island']);
    $ferry = Ferry::factory()->create(['island_id' => $island->id]);

    $order = Order::factory()->create([
        'type' => 'ferry_drops',
        'user_id' => $this->user->id,
    ]);

    $order->ferryDrop()->create([
        'pickup_location' => 'Main Pier',
        'ferry_id' => $ferry->id,
        'island_id' => $island->id,
        'drop_fee' => 20,
        'package_fee' => 10,
    ]);

    $response = $this->getJson('/api/order/get-all?type=ferry_drops');

    $response->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $order->id)
        ->assertJsonPath('data.data.0.island_name', 'Palm Island')
        ->assertJsonPath('data.data.0.restaurant_name', null);
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

test('can cancel an order', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/order/cancel/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'cancelled',
    ]);
});

test('can show order details', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->getJson("/api/order/details/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id);
});

test('can create order with files', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $restaurant = Restaurant::factory()->create();
    $file1 = \Illuminate\Http\UploadedFile::fake()->image('order1.jpg');
    $file2 = \Illuminate\Http\UploadedFile::fake()->create('doc1.pdf', 100);

    $payload = [
        'name' => 'File Order',
        'total_cost' => 50.00,
        'drop_location' => '123 Beach Rd',
        'type' => 'food_delivery',
        'restaurant_id' => $restaurant->id,
        'food_cost' => 40.00,
        'delivery_fee' => 5.00,
        'service_fee' => 5.00,
        'files' => [$file1, $file2],
    ];

    $response = $this->postJson('/api/order/create', $payload);

    $response->assertStatus(201);
    $this->assertCount(2, $response->json('data.files'));

    foreach ($response->json('data.files') as $fileUrl) {
        $urlPath = parse_url($fileUrl, PHP_URL_PATH);
        $path = str_replace('/storage/', '', $urlPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
    }
});

test('can update order with more files', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'food_delivery',
        'files' => ['orders/old.jpg'],
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->image('new.jpg');

    $response = $this->putJson("/api/order/update/{$order->id}", [
        'files' => [$file],
    ]);

    $response->assertStatus(200);
    $this->assertCount(2, $response->json('data.files'));
    $this->assertTrue(collect($response->json('data.files'))->contains(fn ($url) => str_ends_with($url, 'orders/old.jpg')));
});
