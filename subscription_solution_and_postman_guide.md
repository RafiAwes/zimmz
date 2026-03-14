# Subscription Solution and Postman Testing Guide

This document explains what was implemented for Zimmz Plus subscriptions, how the flow works internally, and how to test everything with Postman.

## 1. What Was Implemented

### Business plan implemented
- Plan name: Zimmz Plus
- Price: 150 USD (15000 cents)
- Billing type: recurring Stripe subscription
- Subscription type key in Cashier: default

### Backend components added/updated
- Subscription controller with full lifecycle endpoints:
  - plan
  - subscribe (Stripe Checkout)
  - status
  - billing portal URL generation
  - cancel (end-of-cycle or immediate)
  - resume (grace period)
  - invoices list
  - upcoming invoice
  - invoice PDF download
- Request validation classes:
  - CreateSubscriptionCheckoutRequest
  - CancelSubscriptionRequest
- Stripe webhook listener for subscription/payment events:
  - HandleStripeWebhookForSubscriptions
- Event registration in application boot:
  - AppServiceProvider listens for Laravel\\Cashier\\Events\\WebhookHandled
- Route wiring under authenticated API group:
  - /api/subscription/*
- Config additions in services and app config:
  - Stripe plan-specific config values
  - frontend URL support used for success/cancel redirects

## 2. How It Works

### Plan and security controls
- The system reads the configured Stripe price from STRIPE_PRO_PLAN_ID.
- Before checkout, the server validates the Stripe price directly against Stripe API:
  - active
  - recurring
  - amount = 15000
  - currency = usd (or configured value)
- If a client sends a different price_id than configured, the request is rejected.

### Checkout flow
1. User calls POST /api/subscription/subscribe with JWT token.
2. Server checks:
   - user is not already subscribed
   - user has no incomplete payment
   - plan/price is valid
3. Server creates Stripe Checkout Session through Cashier.
4. Response includes checkout_url.
5. User completes payment on Stripe-hosted page.
6. Stripe sends webhook events.
7. Cashier syncs subscriptions/subscription_items tables.
8. App listener creates user notifications for relevant events.

### Status and lifecycle
- GET /api/subscription/status returns:
  - is_subscribed
  - status (inactive, active, grace_period, ended, etc.)
  - has_incomplete_payment
  - full subscription snapshot and items
- POST /api/subscription/cancel supports:
  - at period end (default)
  - immediate cancel
  - immediate cancel and invoice now
- POST /api/subscription/resume works only if subscription is on grace period.

### Billing and invoices
- GET /api/subscription/billing-portal returns Stripe Billing Portal URL.
- GET /api/subscription/invoices returns historical invoices.
- GET /api/subscription/upcoming-invoice returns next invoice preview.
- GET /api/subscription/invoice/{invoiceId}/download returns a PDF stream.

## 3. API Endpoints Summary

All endpoints below require Authorization: Bearer <jwt_token>.

- GET /api/subscription/plan
- POST /api/subscription/subscribe
- GET /api/subscription/status
- GET /api/subscription/billing-portal
- POST /api/subscription/cancel
- POST /api/subscription/resume
- GET /api/subscription/invoices
- GET /api/subscription/upcoming-invoice
- GET /api/subscription/invoice/{invoiceId}/download

## 4. Required Environment Variables

Set these in .env:

- STRIPE_KEY
- STRIPE_SECRET
- STRIPE_WEBHOOK_SECRET
- STRIPE_PRO_PLAN_ID
- STRIPE_PRODUCT_ID
- STRIPE_PLUS_NAME (default: Zimmz Plus)
- STRIPE_PLUS_AMOUNT (default: 15000)
- STRIPE_PLUS_CURRENCY (default: usd)
- APP_FRONTEND_URL

## 5. Stripe Webhook Requirements

Webhook endpoint:
- POST /stripe/webhook

Enable these Stripe webhook events:
- customer.subscription.created
- customer.subscription.updated
- customer.subscription.deleted
- invoice.payment_succeeded
- invoice.payment_failed

## 6. Postman Setup

### Step A: Create Postman Environment variables
- base_url: your API base URL (example: http://10.10.10.45:8001)
- token: blank initially
- invoice_id: blank initially

### Step B: Login and save JWT token
Request:
- Method: POST
- URL: {{base_url}}/api/auth/login
- Body (raw JSON):

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

Copy data.access_token from response and store it into token variable.

### Step C: Add Authorization in all subscription requests
- Authorization tab
- Type: Bearer Token
- Token: {{token}}

## 7. Postman Test Flow (Recommended Order)

### 1) Check plan
- Method: GET
- URL: {{base_url}}/api/subscription/plan
- Expected:
  - success true
  - data.amount = 15000
  - data.currency = usd

### 2) Start subscription checkout
- Method: POST
- URL: {{base_url}}/api/subscription/subscribe
- Body (raw JSON):

```json
{
  "allow_promotion_codes": true
}
```

Optional body if you want custom redirects:

```json
{
  "allow_promotion_codes": true,
  "success_url": "http://localhost:3000/payment/success",
  "cancel_url": "http://localhost:3000/payment/cancel"
}
```

Expected:
- success true
- data.checkout_url present

Important:
- Checkout completion cannot be finalized inside Postman.
- Open data.checkout_url in browser and complete payment there.

### 3) Verify subscription status after checkout
- Method: GET
- URL: {{base_url}}/api/subscription/status
- Expected after successful payment + webhook sync:
  - data.is_subscribed = true
  - data.status = active (or grace_period if canceled at period end)
  - data.subscription not null

### 4) Open billing portal
- Method: GET
- URL: {{base_url}}/api/subscription/billing-portal
- Expected:
  - data.url present
- Open this URL in browser to test Stripe-hosted plan/payment management.

### 5) Cancel subscription (at period end)
- Method: POST
- URL: {{base_url}}/api/subscription/cancel
- Body (raw JSON):

```json
{
  "immediately": false
}
```

### 6) Cancel subscription immediately
- Method: POST
- URL: {{base_url}}/api/subscription/cancel
- Body (raw JSON):

```json
{
  "immediately": true,
  "invoice_now": false
}
```

### 7) Resume subscription (grace period only)
- Method: POST
- URL: {{base_url}}/api/subscription/resume
- Body: empty

Expected:
- Success only when subscription is on grace period.

### 8) List invoices
- Method: GET
- URL: {{base_url}}/api/subscription/invoices

Optional include pending:
- {{base_url}}/api/subscription/invoices?include_pending=true

Expected:
- data is an array of invoices.

### 9) Upcoming invoice
- Method: GET
- URL: {{base_url}}/api/subscription/upcoming-invoice

### 10) Download invoice PDF
- First, take an invoice id from invoices response.
- Method: GET
- URL: {{base_url}}/api/subscription/invoice/{{invoice_id}}/download
- In Postman, use Send and Download.

## 8. Response Format Pattern

All JSON API responses follow this pattern:

```json
{
  "success": true,
  "status": "success",
  "message": "...",
  "data": {}
}
```

Error responses:

```json
{
  "success": false,
  "status": "error",
  "message": "..."
}
```

## 9. Troubleshooting

### Problem: "Please configure STRIPE_PRO_PLAN_ID before using subscriptions."
- Set STRIPE_PRO_PLAN_ID in .env and clear config cache.

### Problem: "Configured Stripe price amount does not match the Zimmz Plus price."
- Ensure Stripe price is exactly 15000 cents and correct currency.

### Problem: Subscribe succeeds but status still inactive
- Usually webhook not received yet.
- Verify Stripe webhook endpoint and secret.
- Verify required events are enabled.

### Problem: Resume fails
- Resume only works during grace period after cancel at period end.

### Problem: No invoices returned
- User may not have Stripe customer/subscription invoices yet.

## 10. Quick Smoke Checklist

- Login works and JWT is valid
- /subscription/plan returns Zimmz Plus details
- /subscription/subscribe returns checkout_url
- Stripe checkout completes successfully in browser
- /subscription/status becomes active
- /subscription/billing-portal returns URL
- cancel/resume behavior works as expected
- invoices and upcoming invoice endpoints return data
- invoice download works
- notifications are created from webhook events
