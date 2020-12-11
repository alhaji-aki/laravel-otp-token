<?php

namespace AlhajiAki\OtpToken\Traits;

trait CanSendOtpToken
{
    /**
     * Get the column where otp tokens are sent.
     * @param string $field
     * @return string
     */
    public function getColumnForOtpToken($field)
    {
        return $this->{$field};
    }
}
