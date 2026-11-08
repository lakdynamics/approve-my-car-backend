<?php

namespace App\Http\Requests\V1\Profile;

use App\Enums\District;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool|Response
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:150'],
            'line2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', Rule::enum(District::class)],
            'postal_code' => ['nullable', 'digits:5'],
            // A map pin needs both coordinates.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_default' => ['boolean'],
        ];
    }
}
