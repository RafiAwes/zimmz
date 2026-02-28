<?php

use App\Models\User;
use App\Models\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->token = JWTAuth::fromUser($this->user);
    $this->withHeader('Authorization', 'Bearer ' . $this->token);
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('it can fetch all task services', function () {
    TaskService::factory()->count(3)->create();

    $response = $this->getJson('/api/task-service/');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('it can fetch task service details', function () {
    $taskService = TaskService::factory()->create();

    $response = $this->getJson("/api/task-service/details/{$taskService->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $taskService->id);
});

test('it can update a task service', function () {
    $taskService = TaskService::factory()->create();

    $response = $this->putJson("/api/task-service/update/{$taskService->id}", [
        'task' => 'Updated Task',
        'status' => 'pending',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'task' => 'Updated Task',
        'status' => 'pending',
    ]);
});

test('it can delete a task service', function () {
    $taskService = TaskService::factory()->create();

    $response = $this->deleteJson("/api/task-service/delete/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('task_services', ['id' => $taskService->id]);
});

test('it can fetch my tasks as a user', function () {
    TaskService::factory()->create(['user_id' => $this->user->id]);
    TaskService::factory()->create(); // Another user's task

    $response = $this->getJson('/api/task-service/my-tasks');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('it can fetch my tasks as a runner', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    TaskService::factory()->create(['runner_id' => $runner->id]);
    TaskService::factory()->create(); // Another runner's or unassigned task

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/task-service/my-tasks');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('runner can accept a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'new']);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson("/api/task-service/accept/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'pending',
        'runner_id' => $runner->id,
    ]);
});

test('runner can reject a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'pending']);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson("/api/task-service/reject/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'rejected',
    ]);
});

test('runner can complete a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'pending']);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson("/api/task-service/complete/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'completed',
    ]);
});
