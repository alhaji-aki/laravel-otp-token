<?php

namespace AlhajiAki\OtpToken;

use Closure;
use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;
use AlhajiAki\OtpToken\Contracts\OtpTokenBroker as OtpTokenBrokerContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use UnexpectedValueException;

class OtpTokenBroker implements OtpTokenBrokerContract
{
    /**
     * The otp token repository.
     *
     * @var \AlhajiAki\OtpToken\TokenRepositoryInterface
     */
    protected $tokens;

    /**
     * The user provider implementation.
     *
     * @var \Illuminate\Contracts\Auth\UserProvider
     */
    protected $users;

    /**
     * Create a new otp token broker instance.
     *
     * @param  \AlhajiAki\OtpToken\TokenRepositoryInterface  $tokens
     * @param  \Illuminate\Contracts\Auth\UserProvider  $users
     * @return void
     */
    public function __construct(TokenRepositoryInterface $tokens, UserProvider $users)
    {
        $this->users = $users;
        $this->tokens = $tokens;
    }

    /**
     * Send an otp token to a user.
     *
     * @param  array  $credentials
     * @param  \Closure|null  $callback
     * @return string
     */
    public function sendOtpToken(array $credentials, Closure $callback)
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
     * @param  array  $credentials
     * @param  \Closure  $callback
     * @return mixed
     */
    public function performAction(array $credentials, Closure $callback)
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
     * @param  array  $credentials
     * @return \AlhajiAki\OtpToken\Contracts\CanSendOtpToken|string
     */
    protected function validateOtpToken(array $credentials)
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
     * @param  array  $credentials
     * @return \AlhajiAki\OtpToken\Contracts\CanSendOtpToken|null
     *
     * @throws \UnexpectedValueException
     */
    public function getUser(array $credentials)
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
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return string
     */
    public function createToken(CanSendOtpTokenContract $user, $action, $field)
    {
        return $this->tokens->create($user, $action, $field);
    }

    /**
     * Delete otp tokens of the given user.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return void
     */
    public function deleteToken(CanSendOtpTokenContract $user, $action, $field)
    {
        $this->tokens->delete($user, $action, $field);
    }

    /**
     * Validate the given otp token.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param  string  $token
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function tokenExists(CanSendOtpTokenContract $user, $token, $action, $field)
    {
        return $this->tokens->exists($user, $token, $action, $field);
    }

    /**
     * Get the otp token repository implementation.
     *
     * @return \AlhajiAki\OtpToken\TokenRepositoryInterface
     */
    public function getRepository()
    {
        return $this->tokens;
    }
}
