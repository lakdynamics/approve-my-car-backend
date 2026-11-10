<?php

namespace App\Http\Requests\V1\Vehicles\Concerns;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The make is either picked from the list (make_id) or typed in for
 * "Other" (make). Used when adding a vehicle and when booking.
 */
trait ValidatesVehicleMake
{
    /**
     * An active make. When editing, the vehicle's current make is also
     * fine even if an admin has hidden it since.
     */
    protected function listedMake(?Vehicle $vehicle = null): Exists
    {
        $currentMakeId = $vehicle?->make_id;

        return Rule::exists('vehicle_makes', 'id')->where(
            fn ($query) => $query->where(
                fn ($query) => $query->where('is_active', true)
                    ->when($currentMakeId, fn ($query) => $query->orWhere('id', $currentMakeId))
            )
        );
    }

    /**
     * @return array<string, string>
     */
    protected function makeMessages(): array
    {
        return [
            'make_id.exists' => 'Select a make from the list.',
            'make.required_without' => 'Select a make, or choose Other and type it.',
        ];
    }
}
