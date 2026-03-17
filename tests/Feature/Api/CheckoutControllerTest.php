<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\TaskService;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Stripe\StripeClient;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_authenticated_user_can_create_payment_intent_with_manual_capture()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($user);
        $order = Order::factory()->create(['user_id' => $user->id, 'total_cost' => 100.00]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockPaymentIntents = Mockery::mock();

        $mockIntent = new \Stripe\PaymentIntent('pi_test_123');
        $mockIntent->client_secret = 'pi_test_123_secret_abc';
        $mockIntent->status = 'requires_payment_method';

        $mockStripe->paymentIntents = $mockPaymentIntents;
        $mockPaymentIntents->shouldReceive('create')->once()
            ->with(Mockery::on(function ($args) {
                return $args['capture_method'] === 'manual';
            }))
            ->andReturn($mockIntent);

        $this->app->bind(StripeClient::class, function () use ($mockStripe) {
            return $mockStripe;
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/checkout/create-payment-intent', [
                'id' => $order->id,
                'type' => 'order',
            ]);

        $response->assertStatus(201);
    }

    public function test_confirm_payment_updates_status_to_authorized()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'amount' => 100.00,
            'payment_intent_id' => 'pi_test_auth',
            'status' => 'pending',
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockPaymentIntents = Mockery::mock();

        $mockIntent = new \Stripe\PaymentIntent('pi_test_auth');
        $mockIntent->status = 'requires_capture';

        $mockStripe->paymentIntents = $mockPaymentIntents;
        $mockPaymentIntents->shouldReceive('retrieve')->with('pi_test_auth')->andReturn($mockIntent);

        $this->app->bind(StripeClient::class, function () use ($mockStripe) {
            return $mockStripe;
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/checkout/confirm-payment', [
                'payment_intent_id' => 'pi_test_auth',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'authorized');
    }

    public function test_order_approval_captures_payment()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($user);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'user_status' => 'pending_approval',
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'amount' => 100.00,
            'payment_intent_id' => 'pi_to_capture',
            'status' => 'authorized',
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockPaymentIntents = Mockery::mock();

        $mockIntent = new \Stripe\PaymentIntent('pi_to_capture');
        $mockIntent->status = 'succeeded';

        $mockStripe->paymentIntents = $mockPaymentIntents;
        $mockPaymentIntents->shouldReceive('capture')->with('pi_to_capture')->once()->andReturn($mockIntent);

        $this->app->bind(StripeClient::class, function () use ($mockStripe) {
            return $mockStripe;
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/order/approve-delivery/{$order->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    }

    public function test_task_approval_captures_payment()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($user);
        $task = TaskService::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending_approval',
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'payable_type' => TaskService::class,
            'payable_id' => $task->id,
            'amount' => 50.00,
            'payment_intent_id' => 'pi_task_capture',
            'status' => 'authorized',
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockPaymentIntents = Mockery::mock();

        $mockIntent = new \Stripe\PaymentIntent('pi_task_capture');
        $mockIntent->status = 'succeeded';

        $mockStripe->paymentIntents = $mockPaymentIntents;
        $mockPaymentIntents->shouldReceive('capture')->with('pi_task_capture')->once()->andReturn($mockIntent);

        $this->app->bind(StripeClient::class, function () use ($mockStripe) {
            return $mockStripe;
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/task-service/approve/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
