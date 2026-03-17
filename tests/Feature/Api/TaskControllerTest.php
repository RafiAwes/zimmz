<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Notification, Runner, TaskService, User};
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->token = JWTAuth::fromUser($this->user);
    $this->withHeader('Authorization', 'Bearer '.$this->token);
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('it can fetch all task services', function () {
    TaskService::factory()->count(3)->create();

    $response = $this->getJson('/api/task-service/');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data.data');
});

test('it can filter task services by status', function () {
    TaskService::factory()->create(['status' => 'new']);
    TaskService::factory()->create(['status' => 'pending']);

    $response = $this->getJson('/api/task-service/?status=pending');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.status', 'pending');
});

test('it can filter task services by search and status', function () {
    $matchedTask = TaskService::factory()->create([
        'task' => 'Airport transfer',
        'status' => 'pending',
    ]);

    TaskService::factory()->create([
        'task' => 'Airport transfer',
        'status' => 'completed',
    ]);

    TaskService::factory()->create([
        'task' => 'Buy groceries',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/task-service/?search=Airport&status=pending');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $matchedTask->id)
        ->assertJsonPath('data.data.0.status', 'pending');
});

test('it notifies only registered runners when a task is created', function () {
    $registeredRunnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create([
        'user_id' => $registeredRunnerUser->id,
        'type' => 'registered',
    ]);

    $assignedRunnerUser = User::factory()->create(['role' => 'runner']);
    Runner::factory()->create([
        'user_id' => $assignedRunnerUser->id,
        'type' => 'assigned',
    ]);

    $response = $this->postJson('/api/task-service/create', [
        'task' => 'Deliver package to harbor',
        'price' => '50',
    ]);

    $response->assertStatus(201);

    $this->assertTrue(Notification::query()
        ->where('user_id', $registeredRunnerUser->id)
        ->where('type', 'task_created')
        ->exists());

    $this->assertFalse(Notification::query()
        ->where('user_id', $assignedRunnerUser->id)
        ->where('type', 'order_created')
        ->exists());
});

test('it can fetch task service details', function () {
    $taskService = TaskService::factory()->create();

    $response = $this->getJson("/api/task-service/details/{$taskService->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.taskService.id', $taskService->id);
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

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/task-service/my-tasks');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('runner can accept a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'new']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/task-service/accept/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'ongoing',
        'runner_id' => $runner->id,
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $taskService->user_id,
        'type' => 'task_accepted',
    ]);
});

test('runner can reject a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'ongoing']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/task-service/reject/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'new',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $taskService->user_id,
        'type' => 'task_runner_withdrawn',
    ]);
});

test('runner can complete a task', function () {
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($runner);
    $taskService = TaskService::factory()->create(['status' => 'ongoing']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/task-service/complete/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'pending_approval',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $taskService->user_id,
        'type' => 'task_completed',
    ]);
});

test('user can approve a task', function () {
    $user = User::factory()->create(['role' => 'user']);
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($user);
    $taskService = TaskService::factory()->create([
        'user_id' => $user->id,
        'runner_id' => $runner->id,
        'status' => 'pending_approval'
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/task-service/approve/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'completed',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $runner->id,
        'type' => 'task_approved',
    ]);
});

test('user can reject a task completion', function () {
    $user = User::factory()->create(['role' => 'user']);
    $runner = User::factory()->create(['role' => 'runner']);
    $token = JWTAuth::fromUser($user);
    $taskService = TaskService::factory()->create([
        'user_id' => $user->id,
        'runner_id' => $runner->id,
        'status' => 'pending_approval'
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/task-service/user/reject/{$taskService->id}");

    $response->assertStatus(200);
    $this->assertDatabaseHas('task_services', [
        'id' => $taskService->id,
        'status' => 'ongoing',
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $runner->id,
        'type' => 'task_completion_rejected',
    ]);
});
