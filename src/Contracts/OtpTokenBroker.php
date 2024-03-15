<?php

namespace AlhajiAki\OtpToken\Contracts;

use Closure;

interface OtpTokenBroker
{
    /**
     * Constant representing a successfully sent reminder.
     */
    public const OTP_TOKEN_SENT = 'otp_tokens.sent';

    /**
     * Constant representing a successfully performing an action.
     */
    public const ACTION_COMPLETED = 'otp_tokens.completed';

    /**
     * Constant representing the user not found response.
     */
    public const INVALID_USER = 'otp_tokens.user';

    /**
     * Constant representing an invalid token.
     */
    public const INVALID_TOKEN = 'otp_tokens.token';

    /**
     * Constant representing a throttled reset attempt.
     */
    public const OTP_TOKEN_THROTTLED = 'otp_tokens.throttled';

    /**
     * Send an otp token to a user.
     *
     * @param  array<string, string>  $credentials
     */
    public function sendOtpToken(array $credentials, Closure $callback): string;

    /**
     * Perform a certain action on token.
     *
     * @param  array<string, string>  $credentials
     */
    public function performAction(array $credentials, Closure $callback): string;
}
