<?php

use App\Models\Faq;
use App\Models\Page;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->user = User::factory()->create(['role' => 'user']);
});

test('can get a page by title', function () {
    $page = Page::factory()->create(['title' => 'Test Page']);

    getJson("/api/pages/Test Page")
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Test Page');
});

test('admin can create a page', function () {
    actingAs($this->admin, 'api')
        ->postJson('/api/pages', [
            'title' => 'New Page',
            'content' => 'Content here',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'New Page');

    $this->assertDatabaseHas('pages', ['title' => 'New Page']);
});

test('admin can update a page', function () {
    $page = Page::factory()->create();

    actingAs($this->admin, 'api')
        ->putJson("/api/pages/{$page->id}", [
            'title' => 'Updated Title',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title');
});

test('admin can delete a page', function () {
    $page = Page::factory()->create();

    actingAs($this->admin, 'api')
        ->deleteJson("/api/pages/{$page->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
});

test('guest or non-admin cannot create a page', function () {
    postJson('/api/pages', ['title' => 'Fail'])
        ->assertStatus(401);

    actingAs($this->user, 'api')
        ->postJson('/api/pages', ['title' => 'Fail'])
        ->assertStatus(403);
});

test('can get all active faqs', function () {
    Faq::factory()->create(['is_active' => true, 'question' => 'Active?']);
    Faq::factory()->create(['is_active' => false, 'question' => 'Inactive?']);

    getJson('/api/faqs')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.question', 'Active?');
});

test('admin can upsert an faq', function () {
    // Create new
    actingAs($this->admin, 'api')
        ->postJson('/api/faqs/upsert', [
            'question' => 'New FAQ?',
            'answer' => 'Yes.',
        ])
        ->assertStatus(201);

    $this->assertDatabaseHas('faqs', ['question' => 'New FAQ?']);

    // Update existing
    actingAs($this->admin, 'api')
        ->postJson('/api/faqs/upsert', [
            'question' => 'New FAQ?',
            'answer' => 'Updated answer.',
        ])
        ->assertStatus(200);

    $this->assertDatabaseHas('faqs', [
        'question' => 'New FAQ?',
        'answer' => 'Updated answer.',
    ]);
});

test('admin can delete an faq', function () {
    $faq = Faq::factory()->create();

    actingAs($this->admin, 'api')
        ->deleteJson("/api/faqs/{$faq->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
});
