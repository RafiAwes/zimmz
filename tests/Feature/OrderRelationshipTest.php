<?php

use App\Models\Ferry;
use App\Models\FerryDrop;
use App\Models\FoodDelivery;
use App\Models\Island;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an order belongs to a user', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    expect($order->user->id)->toBe($user->id);
    expect($user->refresh()->orders)->toHaveCount(1);
});

test('a food delivery belongs to an order and restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $order = Order::factory()->create(['type' => 'food_delivery']);
    $foodDelivery = FoodDelivery::factory()->create([
        'order_id' => $order->id,
        'restaurant_id' => $restaurant->id,
    ]);

    expect($foodDelivery->order->id)->toBe($order->id);
    expect($foodDelivery->restaurant->id)->toBe($restaurant->id);
    expect($order->refresh()->foodDelivery->id)->toBe($foodDelivery->id);
    expect($restaurant->refresh()->foodDeliveries)->toHaveCount(1);
});

test('a ferry drop belongs to an order, ferry and island', function () {
    $ferry = Ferry::factory()->create();
    $island = Island::factory()->create();
    $order = Order::factory()->create(['type' => 'ferry_drops']);
    $ferryDrop = FerryDrop::factory()->create([
        'order_id' => $order->id,
        'ferry_id' => $ferry->id,
        'island_id' => $island->id,
    ]);

    expect($ferryDrop->order->id)->toBe($order->id);
    expect($ferryDrop->ferry->id)->toBe($ferry->id);
    expect($ferryDrop->island->id)->toBe($island->id);
    expect($order->refresh()->ferryDrop->id)->toBe($ferryDrop->id);
    expect($ferry->refresh()->ferryDrops)->toHaveCount(1);
    expect($island->refresh()->ferryDrops)->toHaveCount(1);
});
