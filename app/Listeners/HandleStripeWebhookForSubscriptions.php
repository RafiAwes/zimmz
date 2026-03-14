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
