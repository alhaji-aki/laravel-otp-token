<?php

namespace AlhajiAki\OtpToken;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class OtpTokenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/otp-tokens.php' => config_path('otp-tokens.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/otp-tokens.php', 'otp-tokens');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('en'),
        ]);
    }

    public function register(): void
    {
        $this->registerOtpTokenBroker();
    }

    protected function registerOtpTokenBroker(): void
    {
        $this->app->singleton('auth.otp_token', function (Application $app) {
            return new OtpTokenBrokerManager($app);
        });

        $this->app->bind('auth.otp_token.broker', function (Application $app) {
            return $app->make('auth.otp_token')->broker();
        });
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['auth.otp_token', 'auth.otp_token.broker'];
    }
}
