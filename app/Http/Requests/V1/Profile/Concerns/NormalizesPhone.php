<?php

namespace App\Http\Requests\V1\Profile\Concerns;

/**
 * Turn the common ways people type a Sri Lankan number into one format,
 * +94XXXXXXXXX, before validation:
 * 0771234567, 771234567, 94771234567, +94 77 123 4567, 077-123-4567.
 */
trait NormalizesPhone
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone)) {
            return;
        }

        $digits = preg_replace('/[\s\-().]/', '', $phone);

        $normalized = match (true) {
            str_starts_with($digits, '+94') => $digits,
            str_starts_with($digits, '94') && strlen($digits) === 11 => '+'.$digits,
            str_starts_with($digits, '0') && strlen($digits) === 10 => '+94'.substr($digits, 1),
            strlen($digits) === 9 && ctype_digit($digits) => '+94'.$digits,
            default => $digits,
        };

        $this->merge(['phone' => $normalized]);
    }
}
