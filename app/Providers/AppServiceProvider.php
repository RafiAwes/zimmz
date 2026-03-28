<?php

namespace App\Providers;

use Illuminate\Support\Facades\{Broadcast, Event};
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;
use App\Listeners\HandleStripeWebhookForSubscriptions;

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
        Broadcast::extend('http', function ($app, $config) {
            return new \App\Broadcasting\HttpBroadcaster($config ?? []);
        });
    }
}
