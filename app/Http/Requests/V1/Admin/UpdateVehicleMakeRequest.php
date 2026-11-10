<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rename and/or hide a make. Send only what changes.
 */
class UpdateVehicleMakeRequest extends StoreVehicleMakeRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50', $this->uniqueName($this->route('vehicleMake'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
