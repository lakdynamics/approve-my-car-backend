<?php

namespace App\Services\Vehicles;

use App\Models\VehicleMake;

/**
 * Turns the validated make fields into what a vehicle stores:
 * make_id (null for "Other") and make (always the display name).
 */
class VehicleMakeResolver
{
    /**
     * @param  array<string, mixed>  $data  Validated vehicle input.
     * @return array<string, mixed>
     */
    public function resolve(array $data): array
    {
        // Picked from the list: use the list's name.
        if (! empty($data['make_id'])) {
            $data['make'] = VehicleMake::findOrFail($data['make_id'])->name;

            return $data;
        }

        // Typed in ("Other", or an older app sending text): link it if it
        // matches a listed make, otherwise keep the text as it is.
        if (isset($data['make'])) {
            $listed = VehicleMake::findActiveByName($data['make']);

            $data['make_id'] = $listed?->id;
            $data['make'] = $listed->name ?? $data['make'];

            return $data;
        }

        return $data;
    }
}
