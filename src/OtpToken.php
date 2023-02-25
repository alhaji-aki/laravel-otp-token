<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\OtpTokenBroker;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string performAction(array $credentials, \Closure $callback)
 * @method static string sendOtpToken(array $credentials, \Closure $callback)
 * @method static \AlhajiAki\OtpToken\Contracts\CanSendOtpToken getUser(array $credentials)
 * @method static string createToken(\AlhajiAki\OtpToken\Contracts\CanSendOtpToken $user, $action, $field)
 * @method static void deleteToken(\AlhajiAki\OtpToken\Contracts\CanSendOtpToken $user, $action, $field)
 * @method static bool tokenExists(\AlhajiAki\OtpToken\Contracts\CanSendOtpToken $user, $token, $action, $field)
 * @method static \AlhajiAki\OtpToken\TokenRepositoryInterface getRepository()
 *
 * @see \AlhajiAki\OtpToken\Contracts\OtpTokenBroker
 */
class OtpToken extends Facade
{
    /**
     * Constant representing a successfully sent token.
     */
    public const OTP_TOKEN_SENT = OtpTokenBroker::OTP_TOKEN_SENT;

    /**
     * Constant representing a successfully performing an action.
     */
    public const ACTION_COMPLETED = OtpTokenBroker::ACTION_COMPLETED;

    /**
     * Constant representing the user not found response.
     */
    public const INVALID_USER = OtpTokenBroker::INVALID_USER;

    /**
     * Constant representing an invalid token.
     */
    public const INVALID_TOKEN = OtpTokenBroker::INVALID_TOKEN;

    /**
     * Constant representing a throttled reset attempt.
     */
    public const OTP_TOKEN_THROTTLED = OtpTokenBroker::OTP_TOKEN_THROTTLED;

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'auth.otp_token';
    }
}
