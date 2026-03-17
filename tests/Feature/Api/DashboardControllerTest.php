<?php

use App\Models\Order;
use App\Models\TaskService;
use App\Models\User;
use Carbon\CarbonImmutable;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-11 10:00:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('admin can get dashboard overview statistics', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create(['role' => 'admin']);
    $userOne = User::factory()->create(['role' => 'user']);
    User::factory()->create(['role' => 'user']);
    User::factory()->create(['role' => 'runner']);

    Order::factory()->create([
        'user_id' => $userOne->id,
        'admin_status' => 'completed',
        'total_cost' => 200.50,
    ]);

    Order::factory()->create([
        'user_id' => $userOne->id,
        'admin_status' => 'pending',
        'total_cost' => 120.00,
    ]);

    TaskService::factory()->create([
        'user_id' => $userOne->id,
        'status' => 'completed',
        'price' => '49.50',
    ]);

    TaskService::factory()->create([
        'user_id' => $userOne->id,
        'status' => 'new',
        'price' => '10.00',
    ]);

    $token = JWTAuth::fromUser($admin);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/overview');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_users', 2)
        ->assertJsonPath('data.total_runners', 1)
        ->assertJsonPath('data.total_task_services', 2)
        ->assertJsonPath('data.total_earnings', 250)
        ->assertJsonPath('data.currency', 'USD');
});

test('admin can get weekly monthly and yearly registration statistics', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create(['role' => 'admin']);
    $token = JWTAuth::fromUser($admin);

    $weekStart = CarbonImmutable::now()->startOfWeek();
    $monthStart = CarbonImmutable::now()->startOfMonth();

    User::factory()->create([
        'role' => 'user',
        'created_at' => $weekStart->addDay(),
        'updated_at' => $weekStart->addDay(),
    ]);

    User::factory()->create([
        'role' => 'user',
        'created_at' => $monthStart->addDays(10),
        'updated_at' => $monthStart->addDays(10),
    ]);

    User::factory()->create([
        'role' => 'runner',
        'created_at' => $weekStart->addDays(2),
        'updated_at' => $weekStart->addDays(2),
    ]);

    $weeklyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/registration-statistics?period=weekly');

    $weeklyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'weekly')
        ->assertJsonPath('data.totals.total_users_registrations', 2)
        ->assertJsonPath('data.totals.total_runners_registrations', 1);

    expect($weeklyResponse->json('data.labels'))->toHaveCount(7);

    $monthlyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/registration-statistics?period=monthly');

    $monthlyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'monthly')
        ->assertJsonPath('data.totals.total_users_registrations', 2)
        ->assertJsonPath('data.totals.total_runners_registrations', 1);

    expect($monthlyResponse->json('data.labels'))->toHaveCount(CarbonImmutable::now()->daysInMonth);

    $yearlyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/registration-statistics?period=yearly');

    $yearlyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'yearly')
        ->assertJsonPath('data.totals.total_users_registrations', 2)
        ->assertJsonPath('data.totals.total_runners_registrations', 1);

    expect($yearlyResponse->json('data.labels'))->toHaveCount(12);
});

test('admin can get weekly, monthly and yearly task service statistics', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->create(['role' => 'admin']);
    $creator = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($admin);
    
    $weekStart = CarbonImmutable::now()->startOfWeek();
    $monthStart = CarbonImmutable::now()->startOfMonth();

    // Weekly/Monthly Task
    TaskService::factory()->create([
        'user_id' => $creator->id,
        'status' => 'completed',
        'price' => '40.00',
        'created_at' => $weekStart->addDay(),
    ]);

    // Monthly Task
    TaskService::factory()->create([
        'user_id' => $creator->id,
        'status' => 'pending',
        'price' => '30.00',
        'created_at' => $monthStart->addDays(10),
    ]);

    // Weekly Response
    $weeklyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/task-service-statistics?period=weekly');

    $weeklyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'weekly')
        ->assertJsonPath('data.totals.total_tasks', 1)
        ->assertJsonPath('data.totals.completed_tasks', 1)
        ->assertJsonPath('data.totals.completed_earnings', 40);

    // Monthly Response
    $monthlyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/task-service-statistics?period=monthly');

    $monthlyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'monthly')
        ->assertJsonPath('data.totals.total_tasks', 2)
        ->assertJsonPath('data.totals.completed_tasks', 1)
        ->assertJsonPath('data.totals.pending_tasks', 1);

    // Yearly Response
    $yearlyResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/dashboard/task-service-statistics?period=yearly');

    $yearlyResponse->assertStatus(200)
        ->assertJsonPath('data.period', 'yearly')
        ->assertJsonPath('data.totals.total_tasks', 2);

    expect($yearlyResponse->json('data.labels'))->toHaveCount(12);
});
