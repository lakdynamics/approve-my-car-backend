<?php

namespace App\Http\Requests\V1\Profile;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

/**
 * Same rules as adding an address, but every field is optional, so the
 * app can send only what changed.
 */
class UpdateAddressRequest extends StoreAddressRequest
{
    /**
     * Check ownership before validating, so someone else's address
     * always gets a 404, never a validation error.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('address'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], parent::rules());

        // No "sometimes" on the coordinates, so sending only one of them
        // still fails: a map pin needs both.
        $rules['latitude'] = parent::rules()['latitude'];
        $rules['longitude'] = parent::rules()['longitude'];

        return $rules;
    }
}
