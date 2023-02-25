<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;

interface TokenRepositoryInterface
{
    /**
     * Create a new token.
     */
    public function create(CanSendOtpTokenContract $user, string $action, string $field): string;

    /**
     * Determine if a token record exists and is valid.
     */
    public function exists(CanSendOtpTokenContract $user, string $token, string $action, string $field): bool;

    /**
     * Determine if the given user recently created an otp token.
     */
    public function recentlyCreatedToken(CanSendOtpTokenContract $user, string $action, string $field): bool;

    /**
     * Delete a token record.
     */
    public function delete(CanSendOtpTokenContract $user, string $action, string $field): void;

    /**
     * Delete expired tokens.
     */
    public function deleteExpired(): void;
}
