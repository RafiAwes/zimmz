<?php

use App\Models\User;
use App\Models\Runner;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mockery::close();
});

test('it redirects to google oauth', function () {
    $response = $this->getJson('/api/auth/google');
    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['url']]);
    
    $url = $response->json('data.url');
    expect($url)->toContain('accounts.google.com');
});

test('it handles google callback and creates a new user', function () {
    $abstractUser = new SocialiteUser();
    $abstractUser->id = '12345';
    $abstractUser->email = 'google@example.com';
    $abstractUser->name = 'Google User';
    
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->getJson('/api/auth/google/callback?state=role%3Duser');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['access_token', 'user']]);

    $this->assertDatabaseHas('users', [
        'email' => 'google@example.com',
        'google_id' => '12345',
        'role' => 'user'
    ]);
});

test('it handles google callback and creates a new runner', function () {
    $abstractUser = new SocialiteUser();
    $abstractUser->id = '67890';
    $abstractUser->email = 'runner@example.com';
    $abstractUser->name = 'Runner User';
    
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->getJson('/api/auth/google/callback?state=role%3Drunner');

    $response->assertStatus(200);

    $this->assertDatabaseHas('users', [
        'email' => 'runner@example.com',
        'role' => 'runner'
    ]);
    
    $user = User::where('email', 'runner@example.com')->first();
    $this->assertDatabaseHas('runners', [
        'user_id' => $user->id,
        'category' => 'food_delivery',
    ]);
});

test('it links existing email account to google id', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $abstractUser = new SocialiteUser();
    $abstractUser->id = '123456';
    $abstractUser->email = 'existing@example.com';
    $abstractUser->name = 'Existing User';
    
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->getJson('/api/auth/google/callback?state=role%3Duser');

    $response->assertStatus(200);

    $user->refresh();
    expect($user->google_id)->toBe('123456');
});
