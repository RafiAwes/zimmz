<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Api\Subscription\CreateSubscriptionCheckoutRequest;
use App\Models\Notification;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Subscription as CashierSubscription;
use RuntimeException;
use Stripe\Price;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SubscriptionController extends Controller
{
    use ApiResponseTraits;

    private const PLAN_KEY = 'zimmz_plus';

    private const SUBSCRIPTION_TYPE = 'default';

    public function plan(): JsonResponse
    {
        $priceId = $this->configuredPriceId();

        if (! $priceId) {
            return $this->errorResponse('Please configure STRIPE_PRO_PLAN_ID before using subscriptions.', 422);
        }

        try {
            $price = $this->retrieveAndValidatePrice($priceId);

            return $this->successResponse([
                ...$this->planSummary(),
                'stripe_product_id' => is_object($price->product) ? $price->product->id : $price->product,
                'interval' => $price->recurring?->interval,
                'interval_count' => $price->recurring?->interval_count,
                'is_active' => (bool) $price->active,
            ], 'Zimmz Plus plan fetched successfully.');
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to fetch plan details: '.$throwable->getMessage(), 422);
        }
    }

    public function subscribe(CreateSubscriptionCheckoutRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();

        $configuredPriceId = $this->configuredPriceId();
        $requestedPriceId = $request->input('price_id');

        if ($configuredPriceId && $requestedPriceId && $requestedPriceId !== $configuredPriceId) {
            return $this->errorResponse('Invalid price selected for Zimmz Plus.', 422);
        }

        $priceId = $configuredPriceId ?: $requestedPriceId;

        if (! $priceId) {
            return $this->errorResponse('Please configure STRIPE_PRO_PLAN_ID before using subscriptions.', 422);
        }

        if ($user->subscribed(self::SUBSCRIPTION_TYPE)) {
            return $this->errorResponse('You are already subscribed.', 400);
        }

        if ($user->hasIncompletePayment(self::SUBSCRIPTION_TYPE)) {
            return $this->errorResponse('You have an incomplete payment. Please complete the existing payment first.', 409);
        }

        try {
            $price = $this->retrieveAndValidatePrice($priceId);

            $successUrl = $request->input('success_url', $this->defaultSuccessUrl());
            $cancelUrl = $request->input('cancel_url', $this->defaultCancelUrl());

            $checkoutBuilder = $user->newSubscription(self::SUBSCRIPTION_TYPE, $priceId)
                ->withMetadata([
                    'plan' => self::PLAN_KEY,
                    'name' => $this->planName(),
                    'user_id' => (string) $user->id,
                ]);

            if ($request->boolean('allow_promotion_codes', true)) {
                $checkoutBuilder->allowPromotionCodes();
            }

            $checkout = $checkoutBuilder->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $user->id,
                'metadata' => [
                    'plan' => self::PLAN_KEY,
                    'user_id' => (string) $user->id,
                ],
            ]);

            $this->createSubscriptionNotification(
                $user,
                'Subscription Checkout Started',
                'Complete your Stripe checkout to activate Zimmz Plus.',
                'subscription_checkout_started'
            );

            return $this->successResponse([
                'checkout_id' => $checkout->id,
                'checkout_url' => $checkout->url,
                'status' => 'pending_payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'plan' => [
                    ...$this->planSummary(),
                    'stripe_product_id' => is_object($price->product) ? $price->product->id : $price->product,
                    'interval' => $price->recurring?->interval,
                    'interval_count' => $price->recurring?->interval_count,
                ],
            ], 'Subscription checkout created successfully.', 201);
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to create subscription checkout: '.$throwable->getMessage(), 422);
        }
    }

    public function status(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $subscription = $user->subscription(self::SUBSCRIPTION_TYPE);

        return $this->successResponse(
            $this->subscriptionStatusPayload($user, $subscription),
            $subscription ? 'Subscription status fetched successfully.' : 'No active subscription found.'
        );
    }

    public function billingPortal(Request $request): JsonResponse
    {
        $request->validate([
            'return_url' => 'nullable|url',
        ]);

        $user = $this->authenticatedUser();

        try {
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $portalUrl = $user->billingPortalUrl(
                $request->input('return_url', $this->defaultBillingReturnUrl())
            );

            return $this->successResponse([
                'url' => $portalUrl,
            ], 'Stripe billing portal URL generated successfully.');
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to generate billing portal URL: '.$throwable->getMessage(), 422);
        }
    }

    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $subscription = $user->subscription(self::SUBSCRIPTION_TYPE);

        if (! $subscription) {
            return $this->errorResponse('No subscription found to cancel.', 404);
        }

        if ($subscription->ended()) {
            return $this->errorResponse('Your subscription has already ended.', 422);
        }

        try {
            if ($request->boolean('immediately')) {
                if ($request->boolean('invoice_now')) {
                    $subscription->cancelNowAndInvoice();
                } else {
                    $subscription->cancelNow();
                }

                $message = 'Subscription canceled immediately.';
            } else {
                $subscription->cancel();
                $message = 'Subscription will be canceled at the end of the billing period.';
            }

            $freshSubscription = $user->fresh()->subscription(self::SUBSCRIPTION_TYPE);

            $this->createSubscriptionNotification(
                $user,
                'Subscription Canceled',
                $message,
                'subscription_canceled',
                $freshSubscription?->id
            );

            return $this->successResponse(
                $this->subscriptionSnapshot($freshSubscription),
                $message
            );
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to cancel subscription: '.$throwable->getMessage(), 422);
        }
    }

    public function resume(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $subscription = $user->subscription(self::SUBSCRIPTION_TYPE);

        if (! $subscription) {
            return $this->errorResponse('No subscription found to resume.', 404);
        }

        if (! $subscription->onGracePeriod()) {
            return $this->errorResponse('Subscription can only be resumed during grace period.', 422);
        }

        try {
            $subscription->resume();
            $freshSubscription = $user->fresh()->subscription(self::SUBSCRIPTION_TYPE);

            $this->createSubscriptionNotification(
                $user,
                'Subscription Resumed',
                'Your Zimmz Plus subscription has been resumed successfully.',
                'subscription_resumed',
                $freshSubscription?->id
            );

            return $this->successResponse(
                $this->subscriptionSnapshot($freshSubscription),
                'Subscription resumed successfully.'
            );
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to resume subscription: '.$throwable->getMessage(), 422);
        }
    }

    public function invoices(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();

        if (! $user->hasStripeId()) {
            return $this->successResponse([], 'No invoices found for this user.');
        }

        try {
            $invoices = $request->boolean('include_pending')
                ? $user->invoicesIncludingPending()
                : $user->invoices();

            return $this->successResponse(
                $invoices->map(fn (Invoice $invoice) => $this->transformInvoice($invoice))->values()->all(),
                'Invoices fetched successfully.'
            );
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to fetch invoices: '.$throwable->getMessage(), 422);
        }
    }

    public function upcomingInvoice(): JsonResponse
    {
        $user = $this->authenticatedUser();

        if (! $user->hasStripeId()) {
            return $this->errorResponse('No Stripe customer is linked to this user.', 404);
        }

        $subscription = $user->subscription(self::SUBSCRIPTION_TYPE);

        if (! $subscription) {
            return $this->errorResponse('No subscription found for this user.', 404);
        }

        try {
            $invoice = $subscription->upcomingInvoice();

            return $this->successResponse(
                $this->transformInvoice($invoice),
                'Upcoming invoice fetched successfully.'
            );
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to fetch upcoming invoice: '.$throwable->getMessage(), 422);
        }
    }

    public function downloadInvoice(string $invoiceId): JsonResponse|Response
    {
        $user = $this->authenticatedUser();

        if (! $user->hasStripeId()) {
            return $this->errorResponse('No Stripe customer is linked to this user.', 404);
        }

        try {
            return $user->downloadInvoice(
                $invoiceId,
                [
                    'vendor' => config('app.name'),
                    'product' => $this->planName(),
                    'email' => config('mail.from.address'),
                    'url' => config('app.url'),
                ],
                'zimmz-plus-'.$invoiceId
            );
        } catch (Throwable $throwable) {
            return $this->errorResponse('Unable to download invoice: '.$throwable->getMessage(), 422);
        }
    }

    private function subscriptionStatusPayload(User $user, ?CashierSubscription $subscription): array
    {
        $isSubscribed = $user->subscribed(self::SUBSCRIPTION_TYPE);

        return [
            'plan' => $this->planSummary(),
            'is_subscribed' => $isSubscribed,
            'status' => $this->resolveSubscriptionState($subscription, $isSubscribed),
            'has_incomplete_payment' => $user->hasIncompletePayment(self::SUBSCRIPTION_TYPE),
            'subscription' => $this->subscriptionSnapshot($subscription),
        ];
    }

    private function subscriptionSnapshot(?CashierSubscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'type' => $subscription->type,
            'stripe_id' => $subscription->stripe_id,
            'stripe_status' => $subscription->stripe_status,
            'stripe_price' => $subscription->stripe_price,
            'quantity' => $subscription->quantity,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'canceled' => $subscription->canceled(),
            'ended' => $subscription->ended(),
            'items' => $subscription->items()->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'stripe_id' => $item->stripe_id,
                    'stripe_product' => $item->stripe_product,
                    'stripe_price' => $item->stripe_price,
                    'quantity' => $item->quantity,
                    'meter_id' => $item->meter_id,
                    'meter_event_name' => $item->meter_event_name,
                ];
            })->values()->all(),
        ];
    }

    private function transformInvoice(Invoice $invoice): array
    {
        $stripeInvoice = $invoice->asStripeInvoice();

        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'currency' => strtolower($invoice->currency),
            'total' => $invoice->total(),
            'raw_total' => $invoice->rawTotal(),
            'amount_due' => $invoice->amountDue(),
            'raw_amount_due' => $invoice->rawAmountDue(),
            'amount_paid' => $invoice->amountPaid(),
            'raw_amount_paid' => $invoice->rawAmountPaid(),
            'created_at' => $invoice->date()->toIso8601String(),
            'due_date' => $invoice->dueDate()?->toIso8601String(),
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
            'invoice_pdf' => $stripeInvoice->invoice_pdf,
            'subscription_id' => $invoice->subscriptionId(),
        ];
    }

    private function retrieveAndValidatePrice(string $priceId): Price
    {
        /** @var Price $price */
        $price = Cashier::stripe()->prices->retrieve($priceId, [
            'expand' => ['product'],
        ]);

        if (! $price->active) {
            throw new RuntimeException('Configured Stripe price is inactive.');
        }

        if (($price->type ?? null) !== 'recurring') {
            throw new RuntimeException('Configured Stripe price must be recurring for subscriptions.');
        }

        if ((int) $price->unit_amount !== $this->expectedPlanAmount()) {
            throw new RuntimeException('Configured Stripe price amount does not match the Zimmz Plus price.');
        }

        if (strtolower((string) $price->currency) !== $this->expectedPlanCurrency()) {
            throw new RuntimeException('Configured Stripe price currency does not match the Zimmz Plus currency.');
        }

        return $price;
    }

    private function planSummary(): array
    {
        $amount = $this->expectedPlanAmount();
        $currency = $this->expectedPlanCurrency();

        return [
            'key' => self::PLAN_KEY,
            'name' => $this->planName(),
            'price_id' => $this->configuredPriceId(),
            'amount' => $amount,
            'currency' => $currency,
            'amount_formatted' => Cashier::formatAmount($amount, $currency),
        ];
    }

    private function resolveSubscriptionState(?CashierSubscription $subscription, bool $isSubscribed): string
    {
        if ($isSubscribed) {
            if ($subscription?->onGracePeriod()) {
                return 'grace_period';
            }

            return 'active';
        }

        if (! $subscription) {
            return 'inactive';
        }

        if ($subscription->ended()) {
            return 'ended';
        }

        return $subscription->stripe_status;
    }

    private function configuredPriceId(): ?string
    {
        $priceId = config('services.stripe.zimmz_plus_price_id');

        if (! is_string($priceId) || $priceId === '') {
            return null;
        }

        return $priceId;
    }

    private function expectedPlanAmount(): int
    {
        return (int) config('services.stripe.zimmz_plus_amount', 15000);
    }

    private function expectedPlanCurrency(): string
    {
        return strtolower((string) config('services.stripe.zimmz_plus_currency', 'usd'));
    }

    private function planName(): string
    {
        return (string) config('services.stripe.zimmz_plus_name', 'Zimmz Plus');
    }

    private function defaultSuccessUrl(): string
    {
        return $this->frontendUrl().'/payment/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function defaultCancelUrl(): string
    {
        return $this->frontendUrl().'/payment/cancel';
    }

    private function defaultBillingReturnUrl(): string
    {
        return $this->frontendUrl().'/billing';
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }

    private function createSubscriptionNotification(
        User $user,
        string $title,
        string $message,
        string $type,
        ?int $relatedId = null
    ): void {
        try {
            Notification::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'related_id' => $relatedId,
            ]);
        } catch (Throwable) {
            // Ignore notification failures so billing flows are not blocked.
        }
    }

    private function authenticatedUser(): User
    {
        $user = Auth::guard('api')->user();

        if (! $user instanceof User) {
            abort(401, 'User not authenticated.');
        }

        return $user;
    }
}
