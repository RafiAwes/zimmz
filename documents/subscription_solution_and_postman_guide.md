# Zimmz Plus Subscription Module

Professional Technical Documentation and Beginner Learning Guide

## 1. Purpose of This Document

This guide explains the subscription implementation end to end so a beginner can understand the module and a professional developer can maintain or extend it safely.

What this guide covers:

1. Business rules and architecture.
2. Every code file involved in the subscription feature.
3. Database structure and request/response contracts.
4. Stripe and webhook integration behavior.
5. Full Postman testing procedure.
6. Failure modes, debugging, and extension tips.

## 2. Business Rules

The current subscription product is Zimmz Plus with these rules:

1. Plan name: Zimmz Plus.
2. Price: 150 USD per billing cycle (15000 cents in Stripe).
3. Stripe price ID is configured in environment.
4. Users authenticate with JWT and call protected API endpoints.
5. Subscription status is managed by Cashier + Stripe webhooks.
6. Lifecycle events generate in-app notifications.

## 3. High-Level Architecture

Main building blocks:

1. API layer.
2. Validation layer (Form Requests).
3. Billing layer (Laravel Cashier + Stripe API).
4. Data layer (users, subscriptions, subscription_items).
5. Event layer (webhook event listener).
6. Notification layer (notifications table).
7. Test layer (Pest feature tests).

### 3.1 Purchase flow summary

1. Client calls subscribe endpoint.
2. Server validates request and current user state.
3. Server validates configured Stripe price (active, recurring, amount, currency).
4. Server creates Stripe Checkout Session via Cashier.
5. Client opens checkout_url in browser and completes payment.
6. Stripe sends webhook events.
7. Cashier updates local subscription tables.
8. Webhook listener creates app notifications.

### 3.2 Management flow summary

1. Status endpoint gives canonical subscription state.
2. Billing portal endpoint returns Stripe portal URL.
3. Cancel endpoint supports scheduled or immediate cancellation.
4. Resume endpoint works only in grace period.
5. Invoice endpoints expose history, upcoming invoice, and PDF download.

## 4. File-by-File Explanation

This section explains each code file used in the implementation.

### 4.1 Controller

File: app/Http/Controllers/Api/SubscriptionController.php

This is the orchestration layer for all subscription operations.

Responsibilities:

1. Expose API endpoints.
2. Enforce business and billing rules.
3. Interact with Cashier/Stripe.
4. Convert domain results into API responses.
5. Write user notifications for key actions.

Key constants:

1. PLAN_KEY = zimmz_plus.
2. SUBSCRIPTION_TYPE = default.

Method-by-method behavior:

1. plan()
2. Purpose: Return canonical plan details from config + Stripe verification.
3. Important checks: STRIPE_PRO_PLAN_ID exists, Stripe price is valid.

1. subscribe(CreateSubscriptionCheckoutRequest $request)
2. Purpose: Create Stripe Checkout Session for subscription.
3. Important checks:
4. Rejects mismatched client price_id when server has a configured price.
5. Rejects if user already subscribed.
6. Rejects if user has incomplete payment.
7. Adds metadata to subscription and checkout.
8. Optional promotion codes supported.
9. Returns checkout_id and checkout_url.

1. status()
2. Purpose: Return normalized subscription state and details.
3. Includes:
4. is_subscribed boolean.
5. status string.
6. has_incomplete_payment.
7. full subscription snapshot with subscription items.

1. billingPortal(Request $request)
2. Purpose: Return Stripe Billing Portal URL for self-service management.
3. Creates Stripe customer if missing.

1. cancel(CancelSubscriptionRequest $request)
2. Purpose: Cancel subscription now or at period end.
3. Options:
4. immediately=false -> cancel at period end.
5. immediately=true -> immediate cancel.
6. invoice_now=true -> immediate cancel with invoice finalization.

1. resume()
2. Purpose: Resume canceled subscription during grace period only.

1. invoices(Request $request)
2. Purpose: Fetch invoice history.
3. Option include_pending=true includes pending invoices.

1. upcomingInvoice()
2. Purpose: Fetch upcoming invoice preview for active subscription.

1. downloadInvoice(string $invoiceId)
2. Purpose: Download invoice PDF from Cashier renderer.

Private helpers and why they matter:

1. subscriptionStatusPayload(): central state payload builder.
2. subscriptionSnapshot(): serializes subscription + items.
3. transformInvoice(): stable invoice JSON representation.
4. retrieveAndValidatePrice(): security control against wrong Stripe price usage.
5. planSummary(): single source of plan constants.
6. resolveSubscriptionState(): maps raw subscription states into API-friendly state.
7. frontendUrl/defaultSuccessUrl/defaultCancelUrl/defaultBillingReturnUrl(): URL policy.
8. createSubscriptionNotification(): creates non-blocking notifications.
9. authenticatedUser(): strict auth guard check.

### 4.2 Request Validation Classes

File: app/Http/Requests/Api/Subscription/CreateSubscriptionCheckoutRequest.php

Purpose:

1. Validate payload for creating checkout.
2. Keep controller logic cleaner.

Validates:

1. price_id as optional string.
2. success_url as optional URL.
3. cancel_url as optional URL.
4. allow_promotion_codes as optional boolean.

File: app/Http/Requests/Api/Subscription/CancelSubscriptionRequest.php

Purpose:

1. Validate cancellation behavior flags.

Validates:

1. immediately as optional boolean.
2. invoice_now as optional boolean.

### 4.3 Webhook Listener

File: app/Listeners/HandleStripeWebhookForSubscriptions.php

Purpose:

1. Listen to handled Stripe webhook payloads from Cashier.
2. Map important Stripe events to user notifications.

Supported events:

1. customer.subscription.created.
2. customer.subscription.updated.
3. customer.subscription.deleted.
4. invoice.payment_succeeded.
5. invoice.payment_failed.

How it finds the user:

1. Reads Stripe customer from payload data.object.customer.
2. Finds user by users.stripe_id.
3. Finds related local subscription by stripe subscription ID.

Design note:

1. If payload/user/event mapping is missing, listener exits safely without failing webhook handling.

### 4.4 Event Registration

File: app/Providers/AppServiceProvider.php

Purpose:

1. Registers listener binding:
2. Laravel\Cashier\Events\WebhookHandled -> HandleStripeWebhookForSubscriptions.

Why this is important:

1. Cashier processes and verifies webhook signatures.
2. After Cashier syncs data, your app adds domain-specific side effects (notifications).

### 4.5 Routes

File: routes/api.php

Subscription route group:

1. Prefix: /api/subscription.
2. Middleware: auth:api.
3. Endpoints:
4. GET /plan.
5. POST /subscribe.
6. GET /status.
7. GET /billing-portal.
8. POST /cancel.
9. POST /resume.
10. GET /invoices.
11. GET /upcoming-invoice.
12. GET /invoice/{invoiceId}/download.

### 4.6 User Model

File: app/Models/User.php

Why it matters:

1. Uses Billable trait from Cashier.
2. Billable provides subscription and invoice APIs used by controller.

Important methods used through Billable:

1. subscribed().
2. newSubscription().
3. subscription().
4. hasIncompletePayment().
5. billingPortalUrl().
6. invoices() and invoicesIncludingPending().
7. downloadInvoice().
8. hasStripeId() and createAsStripeCustomer().

### 4.7 Notification Model

File: app/Models/Notification.php

Purpose:

1. Stores subscription lifecycle notifications.
2. Supports read/unread flow for app notifications.

Relevant fields for subscription feature:

1. user_id.
2. title.
3. message.
4. type.
5. related_id (subscription ID).

### 4.8 Stripe-related Config

File: config/services.php

Stripe plan config keys:

1. stripe.zimmz_plus_name.
2. stripe.zimmz_plus_price_id.
3. stripe.zimmz_plus_product_id.
4. stripe.zimmz_plus_amount.
5. stripe.zimmz_plus_currency.

File: config/app.php

Relevant key:

1. frontend_url.

Used by controller for default success/cancel/portal return URLs.

File: config/cashier.php

Relevant keys:

1. key and secret (Stripe API).
2. webhook.secret and webhook.events.
3. currency and invoice renderer options.

### 4.9 Environment Template

File: .env.example

Subscription-required values:

1. APP_FRONTEND_URL.
2. STRIPE_KEY.
3. STRIPE_SECRET.
4. STRIPE_WEBHOOK_SECRET.
5. STRIPE_PRO_PLAN_ID.
6. STRIPE_PRODUCT_ID.
7. STRIPE_PLUS_NAME.
8. STRIPE_PLUS_AMOUNT.
9. STRIPE_PLUS_CURRENCY.
10. CASHIER_CURRENCY.

### 4.10 Database Migrations Used by Cashier

File: database/migrations/2026_03_12_023525_create_customer_columns.php

Adds customer billing columns to users table:

1. stripe_id.
2. pm_type.
3. pm_last_four.
4. trial_ends_at.

File: database/migrations/2026_03_12_023526_create_subscriptions_table.php

Creates subscriptions table with fields:

1. user_id.
2. type.
3. stripe_id.
4. stripe_status.
5. stripe_price.
6. quantity.
7. trial_ends_at.
8. ends_at.

File: database/migrations/2026_03_12_023527_create_subscription_items_table.php

Creates subscription_items table with fields:

1. subscription_id.
2. stripe_id.
3. stripe_product.
4. stripe_price.
5. quantity.

File: database/migrations/2026_03_12_023528_add_meter_id_to_subscription_items_table.php

Adds meter_id for metered billing compatibility.

File: database/migrations/2026_03_12_023529_add_meter_event_name_to_subscription_items_table.php

Adds meter_event_name for metered event tracking.

### 4.11 Automated Tests

File: tests/Feature/Api/SubscriptionControllerTest.php

Coverage includes:

1. Unauthenticated access rejected for status endpoint.
2. Status returns inactive for non-subscribed user.
3. Subscribe rejects mismatched price ID.
4. Subscribe rejects already subscribed user.
5. Cancel returns not found without subscription.
6. Resume blocked outside grace period.
7. Invoices endpoint empty when no Stripe customer.
8. Webhook listener creates notifications for subscription deletion.

## 5. Data Model Reference

### 5.1 users table billing columns

1. stripe_id: Stripe customer ID.
2. pm_type: default payment method type.
3. pm_last_four: last four digits of default payment method.
4. trial_ends_at: generic trial end timestamp.

### 5.2 subscriptions table

1. One user can have many subscriptions by type.
2. This module uses type=default.
3. stripe_status is authoritative billing state mirror.
4. ends_at enables grace period logic.

### 5.3 subscription_items table

1. Contains price/product line items for a subscription.
2. Needed for multi-price or metered subscriptions.

## 6. API Contract Reference

All endpoints below require:

1. Header Authorization: Bearer <jwt_token>.
2. Header Accept: application/json.

### 6.1 GET /api/subscription/plan

Purpose:

1. Return validated plan metadata.

Success response (example):

```json
{
  "success": true,
  "status": "success",
  "message": "Zimmz Plus plan fetched successfully.",
  "data": {
    "key": "zimmz_plus",
    "name": "Zimmz Plus",
    "price_id": "price_123",
    "amount": 15000,
    "currency": "usd",
    "amount_formatted": "$150.00",
    "stripe_product_id": "prod_123",
    "interval": "month",
    "interval_count": 1,
    "is_active": true
  }
}
```

### 6.2 POST /api/subscription/subscribe

Body options:

```json
{
  "allow_promotion_codes": true,
  "success_url": "http://localhost:3000/payment/success",
  "cancel_url": "http://localhost:3000/payment/cancel"
}
```

Success response includes checkout_url.

### 6.3 GET /api/subscription/status

Returns high-level status plus full subscription snapshot.

### 6.4 GET /api/subscription/billing-portal

Optional query/body field:

1. return_url.

Returns Stripe portal URL in data.url.

### 6.5 POST /api/subscription/cancel

Body options:

```json
{
  "immediately": false,
  "invoice_now": false
}
```

Behavior:

1. immediately=false -> cancel at period end.
2. immediately=true + invoice_now=false -> immediate cancellation.
3. immediately=true + invoice_now=true -> immediate cancellation with invoice finalization.

### 6.6 POST /api/subscription/resume

No body required.

Works only on grace-period subscriptions.

### 6.7 GET /api/subscription/invoices

Optional query:

1. include_pending=true.

Returns invoice array with hosted invoice URLs and PDF URLs.

### 6.8 GET /api/subscription/upcoming-invoice

Returns upcoming invoice preview for active subscription.

### 6.9 GET /api/subscription/invoice/{invoiceId}/download

Streams PDF file as response.

## 7. Security and Correctness Notes

Important controls implemented:

1. Server-side Stripe price validation prevents client price tampering.
2. Price must match expected amount and currency.
3. Authenticated user is resolved with strict guard check.
4. Existing active subscriptions are protected from duplicate purchase attempts.
5. Incomplete payment states are enforced to avoid conflicting checkouts.
6. Webhook listener is idempotent-safe in the sense that unknown payloads are ignored without throwing hard failures.

## 8. Beginner-Friendly Postman Tutorial

### 8.1 One-time setup

1. Create a Postman environment named Local Zimmz.
2. Add variables:
3. base_url = http://10.10.10.45:8001
4. token = (empty)
5. invoice_id = (empty)

### 8.2 Login and store token

Request:

1. Method: POST.
2. URL: {{base_url}}/api/auth/login.
3. Body:

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

Copy data.access_token and set environment variable token.

### 8.3 Authorize subscription requests

1. Open each subscription request.
2. Authorization -> Bearer Token.
3. Use value {{token}}.

### 8.4 Execute the full test journey

Run in this order:

1. GET {{base_url}}/api/subscription/plan.
2. POST {{base_url}}/api/subscription/subscribe.
3. Open data.checkout_url in browser and complete payment.
4. GET {{base_url}}/api/subscription/status.
5. GET {{base_url}}/api/subscription/billing-portal.
6. GET {{base_url}}/api/subscription/invoices.
7. GET {{base_url}}/api/subscription/upcoming-invoice.
8. GET {{base_url}}/api/subscription/invoice/{invoiceId}/download.
9. POST {{base_url}}/api/subscription/cancel.
10. POST {{base_url}}/api/subscription/resume (only if on grace period).

### 8.5 Why browser is required during testing

Stripe Checkout is a hosted payment page. Postman can create the checkout session, but payment completion must happen in browser.

## 9. Stripe Webhook Setup Guide

### 9.1 Endpoint

1. URL: https://<your-domain>/stripe/webhook.
2. Method: POST.

### 9.2 Events to enable

1. customer.subscription.created.
2. customer.subscription.updated.
3. customer.subscription.deleted.
4. invoice.payment_succeeded.
5. invoice.payment_failed.

### 9.3 Secret configuration

1. Copy Stripe webhook signing secret.
2. Set STRIPE_WEBHOOK_SECRET in .env.
3. Clear/reload config cache when deploying.

## 10. Local Verification Commands

Run these commands in project root:

```bash
php artisan route:list --path=api/subscription
php artisan route:list --path=stripe/webhook
php artisan test --compact tests/Feature/Api/SubscriptionControllerTest.php
vendor/bin/pint --dirty
```

## 11. Troubleshooting Guide

Problem: Please configure STRIPE_PRO_PLAN_ID before using subscriptions.

1. Set STRIPE_PRO_PLAN_ID in .env.
2. Run config cache clear/rebuild.

Problem: Configured Stripe price amount does not match the Zimmz Plus price.

1. Confirm Stripe price unit_amount is 15000.
2. Confirm STRIPE_PLUS_AMOUNT and STRIPE_PLUS_CURRENCY values.

Problem: Status remains inactive after payment.

1. Confirm webhook endpoint is reachable by Stripe.
2. Confirm STRIPE_WEBHOOK_SECRET is correct.
3. Confirm required events are enabled.
4. Inspect Stripe event delivery logs.

Problem: Resume fails.

1. Resume only works when subscription is in grace period.
2. If fully ended, create a new checkout session.

Problem: Invoices endpoint empty.

1. User may not yet have Stripe customer/invoice history.
2. Confirm subscription and billing cycles have produced invoices.

## 12. Extension Guide for Future Developers

Safe extension points:

1. Add new plan tiers by extending plan config and introducing plan keys.
2. Add role-based subscription gates via middleware.
3. Add subscription analytics endpoints for admin dashboard.
4. Add trial periods by extending checkout builder options.
5. Add webhook-driven email notifications in addition to DB notifications.

Recommended engineering practices:

1. Keep all price checks server-side.
2. Keep Stripe IDs in env/config, not in client apps.
3. Add feature tests for every new endpoint and branch.
4. Preserve webhook idempotency and fault tolerance.
5. Avoid doing heavy work directly in webhook listener; queue if needed.

## 13. Quick Learning Recap for Beginners

If you remember only these points, you can still work confidently:

1. Subscribe endpoint creates checkout session, not final subscription state.
2. Stripe webhooks are what finalize and sync local subscription records.
3. Status endpoint is your source of truth for current user state.
4. Billing portal is the safest self-service UI for card/plan management.
5. Invoices and upcoming invoice endpoints expose billing visibility.
6. Local feature tests prove behavior before deployment.

## 14. Complete Code Listings (Copy-Paste Ready)

This section includes the actual implementation code for the subscription module files.

### 14.1 app/Http/Controllers/Api/SubscriptionController.php

```php
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
```

### 14.2 app/Http/Requests/Api/Subscription/CreateSubscriptionCheckoutRequest.php

```php
<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionCheckoutRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'price_id' => 'nullable|string',
      'success_url' => 'nullable|url',
      'cancel_url' => 'nullable|url',
      'allow_promotion_codes' => 'sometimes|boolean',
    ];
  }

  public function messages(): array
  {
    return [
      'success_url.url' => 'The success URL must be a valid URL.',
      'cancel_url.url' => 'The cancel URL must be a valid URL.',
    ];
  }
}
```

### 14.3 app/Http/Requests/Api/Subscription/CancelSubscriptionRequest.php

```php
<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'immediately' => 'sometimes|boolean',
      'invoice_now' => 'sometimes|boolean',
    ];
  }
}
```

### 14.4 app/Listeners/HandleStripeWebhookForSubscriptions.php

```php
<?php

namespace App\Listeners;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Cashier\Events\WebhookHandled;

class HandleStripeWebhookForSubscriptions
{
  public function handle(WebhookHandled $event): void
  {
    $eventType = (string) Arr::get($event->payload, 'type', '');
    $stripeCustomerId = (string) Arr::get($event->payload, 'data.object.customer', '');

    if ($eventType === '' || $stripeCustomerId === '') {
      return;
    }

    $user = User::query()->where('stripe_id', $stripeCustomerId)->first();

    if (! $user) {
      return;
    }

    $notification = $this->notificationForEvent($eventType, $event->payload, $user);

    if (! $notification) {
      return;
    }

    Notification::query()->create($notification);
  }

  private function notificationForEvent(string $eventType, array $payload, User $user): ?array
  {
    $subscriptionStripeId = Arr::get($payload, 'data.object.subscription')
      ?? Arr::get($payload, 'data.object.id');

    $relatedId = null;

    if (is_string($subscriptionStripeId) && str_starts_with($subscriptionStripeId, 'sub_')) {
      $relatedId = $user->subscriptions()->where('stripe_id', $subscriptionStripeId)->value('id');
    }

    return match ($eventType) {
      'customer.subscription.created' => $this->buildNotification(
        $user,
        'Zimmz Plus Activated',
        'Your Zimmz Plus subscription is now active.',
        'subscription_created',
        $relatedId
      ),
      'customer.subscription.updated' => $this->buildNotification(
        $user,
        'Subscription Updated',
        'Your Zimmz Plus subscription details were updated.',
        'subscription_updated',
        $relatedId
      ),
      'customer.subscription.deleted' => $this->buildNotification(
        $user,
        'Subscription Ended',
        'Your Zimmz Plus subscription has been canceled.',
        'subscription_deleted',
        $relatedId
      ),
      'invoice.payment_succeeded' => $this->buildNotification(
        $user,
        'Subscription Payment Successful',
        'Your subscription payment was processed successfully.',
        'subscription_payment_succeeded',
        $relatedId
      ),
      'invoice.payment_failed' => $this->buildNotification(
        $user,
        'Subscription Payment Failed',
        'Your subscription payment failed. Please update your payment method in the billing portal.',
        'subscription_payment_failed',
        $relatedId
      ),
      default => null,
    };
  }

  private function buildNotification(
    User $user,
    string $title,
    string $message,
    string $type,
    ?int $relatedId = null
  ): array {
    return [
      'user_id' => $user->id,
      'title' => $title,
      'message' => $message,
      'type' => $type,
      'related_id' => $relatedId,
    ];
  }
}
```

### 14.5 app/Providers/AppServiceProvider.php

```php
<?php

namespace App\Providers;

use App\Listeners\HandleStripeWebhookForSubscriptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    Event::listen(WebhookHandled::class, HandleStripeWebhookForSubscriptions::class);
  }
}
```

### 14.6 routes/api.php (subscription route block)

```php
Route::group(['controller' => SubscriptionController::class, 'prefix' => 'subscription', 'middleware' => 'auth:api'], function () {
  Route::get('/plan', 'plan');
  Route::post('/subscribe', 'subscribe');
  Route::get('/status', 'status');
  Route::get('/billing-portal', 'billingPortal');
  Route::post('/cancel', 'cancel');
  Route::post('/resume', 'resume');
  Route::get('/invoices', 'invoices');
  Route::get('/upcoming-invoice', 'upcomingInvoice');
  Route::get('/invoice/{invoiceId}/download', 'downloadInvoice');
});
```

### 14.7 config/services.php (stripe subscription block)

```php
'stripe' => [
  'zimmz_plus_name' => env('STRIPE_PLUS_NAME', 'Zimmz Plus'),
  'zimmz_plus_price_id' => env('STRIPE_PRO_PLAN_ID'),
  'zimmz_plus_product_id' => env('STRIPE_PRODUCT_ID'),
  'zimmz_plus_amount' => (int) env('STRIPE_PLUS_AMOUNT', 15000),
  'zimmz_plus_currency' => env('STRIPE_PLUS_CURRENCY', env('CASHIER_CURRENCY', 'usd')),
],
```

### 14.8 config/app.php (frontend URL key)

```php
'frontend_url' => env('APP_FRONTEND_URL', env('APP_URL', 'http://localhost')),
```

### 14.9 .env.example (subscription-related keys)

```env
APP_FRONTEND_URL=http://localhost:3000

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_PRO_PLAN_ID=
STRIPE_PRODUCT_ID=
STRIPE_PLUS_NAME="Zimmz Plus"
STRIPE_PLUS_AMOUNT=15000
STRIPE_PLUS_CURRENCY=usd
CASHIER_CURRENCY=usd
```

### 14.10 tests/Feature/Api/SubscriptionControllerTest.php

```php
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
```

### 14.11 Cashier subscription migrations used by this module

```php
// database/migrations/2026_03_12_023525_create_customer_columns.php
Schema::table('users', function (Blueprint $table) {
  $table->string('stripe_id')->nullable()->index();
  $table->string('pm_type')->nullable();
  $table->string('pm_last_four', 4)->nullable();
  $table->timestamp('trial_ends_at')->nullable();
});

// database/migrations/2026_03_12_023526_create_subscriptions_table.php
Schema::create('subscriptions', function (Blueprint $table) {
  $table->id();
  $table->foreignId('user_id');
  $table->string('type');
  $table->string('stripe_id')->unique();
  $table->string('stripe_status');
  $table->string('stripe_price')->nullable();
  $table->integer('quantity')->nullable();
  $table->timestamp('trial_ends_at')->nullable();
  $table->timestamp('ends_at')->nullable();
  $table->timestamps();

  $table->index(['user_id', 'stripe_status']);
});

// database/migrations/2026_03_12_023527_create_subscription_items_table.php
Schema::create('subscription_items', function (Blueprint $table) {
  $table->id();
  $table->foreignId('subscription_id');
  $table->string('stripe_id')->unique();
  $table->string('stripe_product');
  $table->string('stripe_price');
  $table->integer('quantity')->nullable();
  $table->timestamps();

  $table->index(['subscription_id', 'stripe_price']);
});

// database/migrations/2026_03_12_023528_add_meter_id_to_subscription_items_table.php
Schema::table('subscription_items', function (Blueprint $table) {
  $table->string('meter_id')->nullable()->after('stripe_price');
});

// database/migrations/2026_03_12_023529_add_meter_event_name_to_subscription_items_table.php
Schema::table('subscription_items', function (Blueprint $table) {
  $table->string('meter_event_name')->nullable()->after('quantity');
});
```

End of document.
