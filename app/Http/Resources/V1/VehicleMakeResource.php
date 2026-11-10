<?php

namespace App\Http\Resources\V1;

use App\Models\VehicleMake;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleMake
 */
class VehicleMakeResource extends JsonResource
{
    /**
     * The dropdown only needs id and name. Admin screens also get
     * is_active and how many vehicles use the make.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $forAdmin = $request->is('api/*/admin/*');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->when($forAdmin, fn () => $this->is_active),
            'vehicles_count' => $this->when($forAdmin, fn () => (int) ($this->vehicles_count ?? $this->vehicles()->count())),
        ];
    }
}
