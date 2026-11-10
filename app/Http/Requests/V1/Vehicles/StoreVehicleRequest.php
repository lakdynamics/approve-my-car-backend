<?php

namespace App\Http\Requests\V1\Vehicles;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Http\Requests\V1\Vehicles\Concerns\NormalizesVehicleInput;
use App\Http\Requests\V1\Vehicles\Concerns\ValidatesVehicleMake;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreVehicleRequest extends FormRequest
{
    use NormalizesVehicleInput, ValidatesVehicleMake;

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
            'registration_number' => ['required', 'string', 'max:20', $this->uniqueForThisUser()],
            // Either a make from the list, or "Other" with the make typed in.
            'make_id' => ['nullable', 'integer', $this->listedMake()],
            'make' => ['required_without:make_id', 'nullable', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'year' => ['required', 'integer', 'between:1950,'.((int) date('Y') + 1)],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::enum(Transmission::class)],
            'color' => ['nullable', 'string', 'max:30'],
            'mileage' => ['nullable', 'integer', 'between:0,2000000'],
            'chassis_number' => ['nullable', 'string', 'max:50'],
            'engine_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_number.unique' => 'You have already added a vehicle with this registration number.',
            ...$this->makeMessages(),
        ];
    }

    /**
     * One user can't add the same plate twice. Other users can, and a
     * deleted vehicle doesn't block adding it again.
     */
    protected function uniqueForThisUser(?Vehicle $ignore = null): Unique
    {
        return Rule::unique('vehicles', 'registration_number')
            ->where('user_id', $this->user()->id)
            ->withoutTrashed()
            ->ignore($ignore);
    }
}
