<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;
use AlhajiAki\OtpToken\Contracts\OtpTokenBroker as OtpTokenBrokerContract;
use Closure;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use UnexpectedValueException;

class OtpTokenBroker implements OtpTokenBrokerContract
{
    /**
     * Create a new otp token broker instance.
     */
    public function __construct(
        protected TokenRepositoryInterface $tokens,
        protected UserProvider $users
    ) {
    }

    /**
     * Send an otp token to a user.
     *
     * @param  array<string, string>  $credentials
     */
    public function sendOtpToken(array $credentials, Closure $callback): string
    {
        // First we will check to see if we found a user at the given credentials and
        // if we did not we will redirect back to this current URI with a piece of
        // "flash" data in the session to indicate to the developers the errors.
        $user = $this->getUser($credentials);

        if (is_null($user)) {
            return static::INVALID_USER;
        }

        if ($this->tokens->recentlyCreatedToken($user, $credentials['action'], $credentials['field'])) {
            return static::OTP_TOKEN_THROTTLED;
        }

        $token = $this->tokens->create($user, $credentials['action'], $credentials['field']);

        $callback($user, $token);

        return static::OTP_TOKEN_SENT;
    }

    /**
     * Perform a certain action on token.
     *
     * @param  array<string, string>  $credentials
     */
    public function performAction(array $credentials, Closure $callback): string
    {
        $user = $this->validateOtpToken($credentials);

        // If the responses from the validate method is not a user instance, we will
        // assume that it is a redirect and simply return it from this method and
        // the user is properly redirected having an error message on the post.
        if (!$user instanceof CanSendOtpTokenContract) {
            return $user;
        }

        // Once the token has been validated, we'll call the given callback with the user.
        // This gives the user an opportunity to action they want.
        // Then we'll delete the token and return.
        $callback($user);

        $this->tokens->delete($user, $credentials['action'], $credentials['field']);

        return static::ACTION_COMPLETED;
    }

    /**
     * Validate an otp token for the given credentials.
     *
     * @param  array<string, string>  $credentials
     */
    protected function validateOtpToken(array $credentials): CanSendOtpTokenContract|string
    {
        if (is_null($user = $this->getUser($credentials))) {
            return static::INVALID_USER;
        }

        if (!$this->tokens->exists($user, $credentials['token'], $credentials['action'], $credentials['field'])) {
            return static::INVALID_TOKEN;
        }

        return $user;
    }

    /**
     * Get the user for the given credentials.
     *
     * @param  array<string, string>  $credentials
     */
    public function getUser(array $credentials): ?CanSendOtpTokenContract
    {
        $credentials = Arr::except($credentials, ['token', 'field', 'action']);

        $user = $this->users->retrieveByCredentials($credentials);

        if ($user && !$user instanceof CanSendOtpTokenContract) {
            throw new UnexpectedValueException('User must implement CanSendOtpToken interface.');
        }

        return $user;
    }

    /**
     * Create a new otp token for the given user.
     */
    public function createToken(CanSendOtpTokenContract $user, string $action, string $field): string
    {
        return $this->tokens->create($user, $action, $field);
    }

    /**
     * Delete otp tokens of the given user.
     */
    public function deleteToken(CanSendOtpTokenContract $user, string $action, string $field): void
    {
        $this->tokens->delete($user, $action, $field);
    }

    /**
     * Validate the given otp token.
     */
    public function tokenExists(CanSendOtpTokenContract $user, string $token, string $action, string $field): bool
    {
        return $this->tokens->exists($user, $token, $action, $field);
    }

    /**
     * Get the otp token repository implementation.
     */
    public function getRepository(): TokenRepositoryInterface
    {
        return $this->tokens;
    }
}
