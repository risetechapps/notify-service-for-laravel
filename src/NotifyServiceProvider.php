<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;
use RiseTechApps\Notify\Channel;

class NotifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->registerPublishes();

        Notification::extend('notify.sms', function ($app) {
            return new Channel\NotifyChannelSms();
        });

        Notification::extend('notify.mail', function ($app) {
            return new Channel\NotifyChannelMail();
        });

        Notification::extend('notify.push', function ($app) {
            return new Channel\NotifyChannelPush();
        });

        Notification::extend('notify.apns', function ($app) {
            return new Channel\NotifyChannelApns();
        });

        Notification::extend('notify.telegram', function ($app) {
            return new Channel\NotifyChannelTelegram();
        });

        Notification::extend('notify.slack', function ($app) {
            return new Channel\NotifyChannelSlack();
        });

        Notification::extend('notify.discord', function ($app) {
            return new Channel\NotifyChannelDiscord();
        });

        Notification::extend('notify.teams', function ($app) {
            return new Channel\NotifyChannelTeams();
        });

        Notification::extend('notify.websocket', function ($app) {
            return new Channel\NotifyChannelWebSocket();
        });

        Notification::extend('notify.webhook', function ($app) {
            return new Channel\NotifyChannelWebhook();
        });
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'notify');

        // Register the main class to use with the facade
        $this->app->singleton(Notify::class, function () {
            return new Notify();
        });
    }

    protected function registerPublishes(): void
    {
        if ($this->app->runningInConsole()) {

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('notify.php'),
            ], 'config');
        }
    }

}
