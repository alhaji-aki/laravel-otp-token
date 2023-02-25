<?php

namespace AlhajiAki\OtpToken\Traits;

trait CanSendOtpToken
{
    /**
     * Get the column where otp tokens are sent.
     */
    public function getColumnForOtpToken(string $field): string
    {
        return $this->{$field};
    }
}
