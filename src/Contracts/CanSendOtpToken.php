<?php

namespace AlhajiAki\OtpToken\Contracts;

interface CanSendOtpToken
{
    /**
     * Get the column where otp tokens are sent.
     */
    public function getColumnForOtpToken(string $field): string;
}
