<?php

namespace AlhajiAki\OtpToken\Contracts;

interface CanSendOtpToken
{
    /**
     * Get the column where otp tokens are sent.
     * @param string $field
     * @return string
     */
    public function getColumnForOtpToken($field);
}
