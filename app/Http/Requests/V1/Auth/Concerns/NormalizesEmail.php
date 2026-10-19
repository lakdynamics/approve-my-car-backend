<?php

namespace App\Http\Requests\V1\Auth\Concerns;

use Illuminate\Support\Str;

/**
 * Lowercase the email before validation, so "Nimal@Example.com" and
 * "nimal@example.com" always mean the same account. Laravel's TrimStrings
 * middleware has already removed surrounding spaces.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }
}
