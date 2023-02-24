<?php

namespace AlhajiAki\OtpToken;

use Illuminate\Support\ServiceProvider;

class OtpTokenServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/otp-tokens.php' => config_path('otp-tokens.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/otp-tokens.php', 'otp-tokens');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('lang/en'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerOtpTokenBroker();
    }

    /**
     * Register the otp token broker instance.
     *
     * @return void
     */
    protected function registerOtpTokenBroker()
    {
        $this->app->singleton('auth.otp_token', function ($app) {
            return new OtpTokenBrokerManager($app);
        });

        $this->app->bind('auth.otp_token.broker', function ($app) {
            return $app->make('auth.otp_token')->broker();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['auth.otp_token', 'auth.otp_token.broker'];
    }
}
