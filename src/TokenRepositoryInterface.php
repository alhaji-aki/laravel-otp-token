<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;

interface TokenRepositoryInterface
{
    /**
     * Create a new token.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return string
     */
    public function create(CanSendOtpTokenContract $user, $action, $field);

    /**
     * Determine if a token record exists and is valid.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param  string  $token
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function exists(CanSendOtpTokenContract $user, $token, $action, $field);

    /**
     * Determine if the given user recently created an otp token.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function recentlyCreatedToken(CanSendOtpTokenContract $user, $action, $field);

    /**
     * Delete a token record.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return void
     */
    public function delete(CanSendOtpTokenContract $user, $action, $field);

    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function deleteExpired();
}
