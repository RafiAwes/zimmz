<?php

use App\Listeners\HandleStripeWebhookForSubscriptions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookHandled;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access subscription status', function () {
    /** @var \Tests\TestCase $this */
    $this->getJson('/api/subscription/status')
        ->assertStatus(401);
});

test('subscription status returns inactive for user without subscription', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/subscription/status')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.is_subscribed', false)
        ->assertJsonPath('data.plan.name', 'Zimmz Plus');
});

test('subscribe rejects mismatched price id', function () {
    /** @var \Tests\TestCase $this */
    config()->set('services.stripe.zimmz_plus_price_id', 'price_expected');

    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/subscription/subscribe', [
            'price_id' => 'price_tampered',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid price selected for Zimmz Plus.');
});

test('subscribe returns already subscribed for active subscribers', function () {
    /** @var \Tests\TestCase $this */
    config()->set('services.stripe.zimmz_plus_price_id', 'price_expected');

    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_active_'.fake()->unique()->numerify('#######'),
        'stripe_status' => 'active',
        'stripe_price' => 'price_expected',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/subscription/subscribe', [
            'price_id' => 'price_expected',
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', 'You are already subscribed.');
});

test('cancel returns not found when user has no subscription', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/subscription/cancel')
        ->assertStatus(404)
        ->assertJsonPath('message', 'No subscription found to cancel.');
});

test('resume can only happen during grace period', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_resume_'.fake()->unique()->numerify('#######'),
        'stripe_status' => 'active',
        'stripe_price' => 'price_expected',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/subscription/resume')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Subscription can only be resumed during grace period.');
});

test('invoices endpoint returns empty data when stripe customer is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/subscription/invoices')
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

test('stripe webhook listener stores notification for subscription events', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['role' => 'user']);
    $user->forceFill(['stripe_id' => 'cus_webhook_123'])->save();

    $subscription = $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_webhook_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_expected',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_webhook_123',
                'customer' => 'cus_webhook_123',
            ],
        ],
    ];

    app(HandleStripeWebhookForSubscriptions::class)
        ->handle(new WebhookHandled($payload));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'title' => 'Subscription Ended',
        'type' => 'subscription_deleted',
        'related_id' => $subscription->id,
    ]);
});
