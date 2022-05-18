<?php

namespace AlhajiAki\OtpToken\Contracts;

use Closure;

interface OtpTokenBroker
{
    /**
     * Constant representing a successfully sent reminder.
     *
     * @var string
     */
    public const OTP_TOKEN_SENT = 'otp_tokens.sent';

    /**
     * Constant representing a successfully performing an action.
     *
     * @var string
     */
    public const ACTION_COMPLETED = 'otp_tokens.completed';

    /**
     * Constant representing the user not found response.
     *
     * @var string
     */
    public const INVALID_USER = 'otp_tokens.user';

    /**
     * Constant representing an invalid token.
     *
     * @var string
     */
    public const INVALID_TOKEN = 'otp_tokens.token';

    /**
     * Constant representing a throttled reset attempt.
     *
     * @var string
     */
    public const OTP_TOKEN_THROTTLED = 'otp_tokens.throttled';

    /**
     * Send an otp token to a user.
     *
     * @param  array  $credentials
     * @param  \Closure|null  $callback
     * @return string
     */
    public function sendOtpToken(array $credentials, Closure $callback);

    /**
     * Perform a certain action on token.
     *
     * @param  array  $credentials
     * @param  \Closure  $callback
     * @return mixed
     */
    public function performAction(array $credentials, Closure $callback);
}
