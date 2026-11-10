<?php

namespace App\Http\Requests\V1\Admin;

use App\Models\VehicleMake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreVehicleMakeRequest extends FormRequest
{
    /**
     * The route's "can:admin" middleware already checked who is calling.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::squish($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueName()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This make already exists.',
        ];
    }

    /**
     * Unique ignoring case (the MySQL collation is case-insensitive), so
     * "toyota" is rejected when "Toyota" exists.
     */
    protected function uniqueName(?VehicleMake $ignore = null): Unique
    {
        return Rule::unique('vehicle_makes', 'name')->ignore($ignore);
    }
}
