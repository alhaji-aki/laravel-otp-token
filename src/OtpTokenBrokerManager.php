<?php

namespace AlhajiAki\OtpToken;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OtpTokenBrokerManager
{
    /**
     * The array of created "drivers".
     *
     * @var array<string, OtpTokenBroker>
     */
    protected array $brokers = [];

    /**
     * Create a new OtpTokenBroker manager instance.
     */
    public function __construct(
        protected Application $app
    ) {
    }

    /**
     * Attempt to get the broker from the local cache.
     */
    public function broker(string $name = null): OtpTokenBroker
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->brokers[$name] ?? ($this->brokers[$name] = $this->resolve($name));
    }

    /**
     * Resolve the given broker.
     */
    protected function resolve(string $name): OtpTokenBroker
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Otp Token provider [{$name}] is not defined.");
        }

        // The otp token broker uses a token repository to validate tokens and send user
        // otp tokens, as well as providing a convenient interface for otp token verification.
        return new OtpTokenBroker(
            $this->createTokenRepository($config),
            $this->app['auth']->createUserProvider($config['provider'])
        );
    }

    /**
     * Create a token repository instance based on the given configuration.
     *
     * @param  array{"table": string, "expire": int, "throttle"?: int, "connection"?: string}  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $key = $this->app['config']['app.key'];

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $connection = $config['connection'] ?? null;

        return new DatabaseTokenRepository(
            $this->app['db']->connection($connection),
            $this->app['hash'],
            $config['table'],
            $key,
            $config['expire'],
            $config['throttle'] ?? 0
        );
    }

    /**
     * Get the otp token broker configuration.
     *
     * @return array{"provider": string, "table": string, "expire": int, "throttle"?: int, "connection"?: string}
     */
    protected function getConfig(string $name): ?array
    {
        return $this->app['config']["otp-tokens.otp_tokens.{$name}"];
    }

    /**
     * Get the default otp token broker name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app['config']['otp-tokens.defaults.otp_tokens'];
    }

    /**
     * Set the default otp token broker name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->app['config']['otp-tokens.defaults.otp_tokens'] = $name;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  array<int, string>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->broker()->{$method}(...$parameters);
    }
}
