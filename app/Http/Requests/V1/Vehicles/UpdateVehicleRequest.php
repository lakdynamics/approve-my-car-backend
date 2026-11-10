<?php

namespace App\Http\Requests\V1\Vehicles;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

/**
 * Same rules as adding a vehicle, but every field is optional, so the
 * app can send only what changed.
 */
class UpdateVehicleRequest extends StoreVehicleRequest
{
    /**
     * Check ownership before validating, so someone else's vehicle
     * always gets a 404, never a validation error.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('vehicle'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Keeping the vehicle's own registration number is not a duplicate.
        $rules['registration_number'] = ['required', 'string', 'max:20', $this->uniqueForThisUser($this->route('vehicle'))];
        $rules['make_id'] = ['nullable', 'integer', $this->listedMake($this->route('vehicle'))];

        return array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);
    }
}
