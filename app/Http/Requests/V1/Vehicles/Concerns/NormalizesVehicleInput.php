<?php

namespace App\Http\Requests\V1\Vehicles\Concerns;

use Illuminate\Support\Str;

/**
 * Clean up identifiers before validation, so "wp  cab 1234" and
 * "WP CAB 1234" count as the same plate. Laravel's TrimStrings
 * middleware has already removed surrounding spaces.
 */
trait NormalizesVehicleInput
{
    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (['registration_number', 'chassis_number', 'engine_number'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = Str::upper(Str::squish($this->input($field)));
            }
        }

        $this->merge($clean);
    }
}
